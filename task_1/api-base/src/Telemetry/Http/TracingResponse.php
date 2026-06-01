<?php

namespace Ufz\ApiBase\Telemetry\Http;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TracingResponse implements ResponseInterface
{
    private ResponseInterface $response;
    private ?object $span;
    private ?LoggerInterface $logger;
    private ?float $slowRequestThresholdMs;
    private string $method;
    private string $url;
    private float $startTime;
    private bool $ended = false;

    public function __construct(
        ResponseInterface $response,
        ?object $span,
        ?LoggerInterface $logger,
        ?float $slowRequestThresholdMs,
        string $method,
        string $url,
        float $startTime
    ) {
        $this->response = $response;
        $this->span = $span;
        $this->logger = $logger;
        $this->slowRequestThresholdMs = $slowRequestThresholdMs;
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->startTime = $startTime;
    }

    public function getInnerResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        try {
            $status = $this->response->getStatusCode();
        } catch (\Throwable $exception) {
            $this->finish(null, $exception);
            throw $exception;
        }

        $this->finish($status, null);

        return $status;
    }

    public function getHeaders(bool $throw = true): array
    {
        try {
            $headers = $this->response->getHeaders($throw);
        } catch (\Throwable $exception) {
            $this->finish(null, $exception);
            throw $exception;
        }

        $this->finish($this->getInfoHttpCode(), null);

        return $headers;
    }

    public function getContent(bool $throw = true): string
    {
        try {
            $content = $this->response->getContent($throw);
        } catch (\Throwable $exception) {
            $this->finish($this->getInfoHttpCode(), $exception);
            throw $exception;
        }

        $this->finish($this->getInfoHttpCode(), null);

        return $content;
    }

    public function toArray(bool $throw = true): array
    {
        try {
            $data = $this->response->toArray($throw);
        } catch (\Throwable $exception) {
            $this->finish($this->getInfoHttpCode(), $exception);
            throw $exception;
        }

        $this->finish($this->getInfoHttpCode(), null);

        return $data;
    }

    public function cancel(): void
    {
        $this->response->cancel();
        $this->finish($this->getInfoHttpCode(), null);
    }

    public function getInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    public function finishFromStream(?string $error = null): void
    {
        $exception = null;
        if (is_string($error) && $error !== '') {
            $exception = new \RuntimeException($error);
        }

        $this->finish($this->getInfoHttpCode(), $exception);
    }

    private function finish(?int $statusCode, ?\Throwable $exception): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;

        $durationMs = (microtime(true) - $this->startTime) * 1000;

        if (is_object($this->span)) {
            if (method_exists($this->span, 'setAttribute')) {
                if ($statusCode !== null) {
                    $this->span->setAttribute('http.status_code', $statusCode);
                }
                $this->span->setAttribute('http.duration_ms', round($durationMs, 2));
            }

            if ($exception !== null && method_exists($this->span, 'recordException')) {
                $this->span->recordException($exception);
            }

            if (method_exists($this->span, 'setStatus') && class_exists(\OpenTelemetry\API\Trace\StatusCode::class)) {
                if ($exception !== null) {
                    $this->span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $exception->getMessage());
                } elseif ($statusCode !== null && $statusCode >= 400) {
                    $this->span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
                }
            }

            if (method_exists($this->span, 'end')) {
                $this->span->end();
            }
        }

        $this->logSlowRequest($durationMs, $statusCode, $exception);
    }

    private function logSlowRequest(float $durationMs, ?int $statusCode, ?\Throwable $exception): void
    {
        if ($this->slowRequestThresholdMs === null || $durationMs < $this->slowRequestThresholdMs) {
            return;
        }

        $context = [
            'duration_ms' => round($durationMs, 2),
            'method' => $this->method,
            'url' => $this->url,
            'status_code' => $statusCode,
        ];
        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
        }

        if ($this->logger) {
            $this->logger->warning('slow_http_request', $context);
            return;
        }

        error_log('slow_http_request ' . json_encode($context));
    }

    private function getInfoHttpCode(): ?int
    {
        $code = $this->response->getInfo('http_code');
        if (is_int($code)) {
            return $code;
        }

        if (is_string($code) && ctype_digit($code)) {
            return (int) $code;
        }

        return null;
    }
}
