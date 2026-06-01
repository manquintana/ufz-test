<?php

namespace Ufz\ApiBase\Telemetry\Http;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Ufz\ApiBase\Telemetry\TracerFactory;

final class TracingHttpClient implements HttpClientInterface
{
    private const DEFAULT_SLOW_HTTP_MS = 500.0;

    private HttpClientInterface $client;
    private TracerFactory $tracerFactory;
    private ?LoggerInterface $logger;
    private ?float $slowRequestThresholdMs;

    public function __construct(
        HttpClientInterface $client,
        TracerFactory $tracerFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->tracerFactory = $tracerFactory;
        $this->logger = $logger;
        $this->slowRequestThresholdMs = $this->readSlowRequestThreshold('UFZ_SLOW_HTTP_MS');
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $span = $this->startSpan($method, $url);
        $start = microtime(true);
        $response = $this->client->request($method, $url, $options);

        return new TracingResponse(
            $response,
            $span,
            $this->logger,
            $this->slowRequestThresholdMs,
            $method,
            $url,
            $start
        );
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        $map = new \SplObjectStorage();
        $innerResponses = [];

        foreach ($responses as $response) {
            if ($response instanceof TracingResponse) {
                $inner = $response->getInnerResponse();
                $map[$inner] = $response;
                $innerResponses[] = $inner;
                continue;
            }

            $innerResponses[] = $response;
        }

        $stream = $this->client->stream($innerResponses, $timeout);

        if ($map->count() === 0) {
            return $stream;
        }

        return new TracingResponseStream($stream, $map);
    }

    public function withOptions(array $options): static
    {
        return new self(
            $this->client->withOptions($options),
            $this->tracerFactory,
            $this->logger
        );
    }

    private function startSpan(string $method, string $url): ?object
    {
        $tracer = $this->tracerFactory->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            return null;
        }

        $spanName = sprintf('HTTP %s', strtoupper($method));
        $builder = $tracer->spanBuilder($spanName);
        if (class_exists(\OpenTelemetry\API\Trace\SpanKind::class) && method_exists($builder, 'setSpanKind')) {
            $builder->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CLIENT);
        }

        $span = $builder->startSpan();
        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('http.method', strtoupper($method));
            $span->setAttribute('http.url', $url);

            $parts = parse_url($url);
            if (is_array($parts)) {
                $scheme = $parts['scheme'] ?? null;
                $host = $parts['host'] ?? null;
                $port = $parts['port'] ?? null;
                $path = $parts['path'] ?? '/';
                $query = $parts['query'] ?? '';

                if (is_string($scheme) && $scheme !== '') {
                    $span->setAttribute('http.scheme', $scheme);
                }
                if (is_string($host) && $host !== '') {
                    $span->setAttribute('http.host', $host);
                    $span->setAttribute('net.peer.name', $host);
                }
                if (is_int($port)) {
                    $span->setAttribute('net.peer.port', $port);
                }
                if (is_string($path) && $path !== '') {
                    $target = $path;
                    if (is_string($query) && $query !== '') {
                        $target .= '?' . $query;
                    }
                    $span->setAttribute('http.target', $target);
                }
            }
        }

        return $span;
    }

    private function readSlowRequestThreshold(string $name): ?float
    {
        $raw = getenv($name);
        if ($raw === false) {
            return self::DEFAULT_SLOW_HTTP_MS;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return self::DEFAULT_SLOW_HTTP_MS;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['off', 'false', 'none'], true)) {
            return null;
        }

        if (!is_numeric($raw)) {
            return self::DEFAULT_SLOW_HTTP_MS;
        }

        $value = (float) $raw;
        return $value > 0 ? $value : null;
    }
}
