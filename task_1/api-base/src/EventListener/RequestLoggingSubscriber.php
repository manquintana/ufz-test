<?php

namespace Ufz\ApiBase\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestLoggingSubscriber implements EventSubscriberInterface
{
    private const SPAN_ATTRIBUTE = '_otel_span';
    private const DEFAULT_SLOW_REQUEST_MS = 1000.0;
    private const EXCLUDED_PATHS = ['/health', '/health/ready'];

    private ?LoggerInterface $logger;
    private ?float $slowRequestThresholdMs;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->slowRequestThresholdMs = $this->readSlowRequestThreshold('UFZ_SLOW_REQUEST_MS');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (in_array($request->getPathInfo(), self::EXCLUDED_PATHS, true)) {
            return;
        }

        $response = $event->getResponse();
        $incomingTraceContext = $this->extractIncomingTraceContext($request);
        $graphql = $this->extractGraphqlInfo($request);

        $context = [
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query' => $graphql['query'] ?? $request->getQueryString(),
            'graphql_operation_name' => $graphql['operation_name'],
            'graphql_operation_type' => $graphql['operation_type'],
            'request_uri' => $request->getRequestUri(),
            'request' => $request->getMethod().' '.$request->getRequestUri(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'http_version' => $request->getProtocolVersion(),
            'route' => $request->attributes->get('_route'),
            'status' => $response->getStatusCode(),
            'request_id' => $this->firstHeader($request, [
                'x-request-id',
                'x-correlation-id',
            ]),
            'trace_id' => $incomingTraceContext['trace_id'],
            'span_id' => $incomingTraceContext['span_id'],
            'trace_flags' => $incomingTraceContext['trace_flags'],
            'client_ip' => $request->getClientIp(),
            'forwarded_for' => $request->headers->get('x-forwarded-for'),
            'user_agent' => $request->headers->get('user-agent'),
            'referer' => $request->headers->get('referer'),
            'request_content_type' => $request->headers->get('content-type'),
            'request_content_length' => $request->headers->get('content-length'),
            'response_content_type' => $response->headers->get('content-type'),
            'response_content_length' => $response->headers->get('content-length'),
            'duration_ms' => $this->getDurationMs($request),
        ];

        if ($context['trace_id'] === null || $context['span_id'] === null || $context['trace_flags'] === null) {
            $spanContext = $this->getSpanContext($request);
            if ($context['trace_id'] === null) {
                $context['trace_id'] = $spanContext['trace_id'];
            }
            if ($context['span_id'] === null) {
                $context['span_id'] = $spanContext['span_id'];
            }
            if ($context['trace_flags'] === null) {
                $context['trace_flags'] = $spanContext['trace_flags'];
            }
        }

        $graphqlErrors = $this->extractGraphqlErrors($response);
        if ($graphqlErrors !== null) {
            $context['graphql_errors'] = $graphqlErrors;
        }

        if ($this->logger) {
            if ($graphqlErrors !== null) {
                $this->logger->error('http_request', $context);
            } else {
                $this->logger->info('http_request', $context);
            }
            $this->logSlowRequest($context);
            return;
        }

        $payload = ['message' => 'http_request'] + $context;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log('http_request');
        } else {
            error_log($json);
        }

        $this->logSlowRequest($context);
    }

    /**
     * Extracts GraphQL errors from an HTTP 200 response body.
     * API Platform returns GraphQL errors as HTTP 200 with errors in the JSON body,
     * which means they bypass normal HTTP error logging.
     *
     * @return array<mixed>|null The errors array, or null if no errors found.
     */
    private function extractGraphqlErrors(\Symfony\Component\HttpFoundation\Response $response): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $contentType = (string) $response->headers->get('content-type', '');
        if (stripos($contentType, 'application/json') === false) {
            return null;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['errors']) && is_array($data['errors']) && !empty($data['errors'])) {
            return $data['errors'];
        }

        return null;
    }

    private function getDurationMs(Request $request): float
    {
        $start = $request->server->get('REQUEST_TIME_FLOAT');
        if (!is_numeric($start)) {
            $start = $request->server->get('REQUEST_TIME');
        }
        if (!is_numeric($start)) {
            $start = microtime(true);
        }

        return round((microtime(true) - (float) $start) * 1000, 2);
    }

    private function firstHeader(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $request->headers->get($name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{query: ?string, operation_name: ?string, operation_type: ?string}
     */
    private function extractGraphqlInfo(Request $request): array
    {
        $queryParam = $request->query->get('query');
        $operationParam = $request->query->get('operationName');
        if (is_string($queryParam) && $queryParam !== '') {
            return $this->buildGraphqlInfo($queryParam, $this->stringOrNull($operationParam));
        }

        $queryBody = $request->request->get('query');
        $operationBody = $request->request->get('operationName');
        if (is_string($queryBody) && $queryBody !== '') {
            return $this->buildGraphqlInfo($queryBody, $this->stringOrNull($operationBody));
        }

        $contentType = (string) $request->headers->get('content-type', '');
        $raw = $request->getContent();
        if (!is_string($raw) || $raw === '') {
            return ['query' => null, 'operation_name' => null, 'operation_type' => null];
        }

        if (stripos($contentType, 'application/graphql') !== false) {
            return $this->buildGraphqlInfo($raw, null);
        }

        if (stripos($contentType, 'application/json') === false) {
            return ['query' => null, 'operation_name' => null, 'operation_type' => null];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['query' => null, 'operation_name' => null, 'operation_type' => null];
        }

        $query = $data['query'] ?? null;
        if (is_string($query) && $query !== '') {
            return $this->buildGraphqlInfo($query, $this->stringOrNull($data['operationName'] ?? null));
        }

        if (isset($data[0]) && is_array($data[0])) {
            $batchQuery = $data[0]['query'] ?? null;
            if (is_string($batchQuery) && $batchQuery !== '') {
                return $this->buildGraphqlInfo($batchQuery, $this->stringOrNull($data[0]['operationName'] ?? null));
            }
        }

        return ['query' => null, 'operation_name' => null, 'operation_type' => null];
    }

    /**
     * @return array{query: string, operation_name: ?string, operation_type: ?string}
     */
    private function buildGraphqlInfo(string $query, ?string $explicitOperationName): array
    {
        $operations = $this->parseGraphqlOperations($query);
        $operationName = $explicitOperationName;
        $operationType = null;

        if ($operationName !== null) {
            foreach ($operations as $op) {
                if ($op['name'] === $operationName) {
                    $operationType = $op['type'];
                    break;
                }
            }
        } elseif ($operations !== []) {
            $operationName = $operations[0]['name'];
            $operationType = $operations[0]['type'];
        }

        return [
            'query' => $query,
            'operation_name' => $operationName,
            'operation_type' => $operationType,
        ];
    }

    /**
     * Best-effort parse of GraphQL operation headers (e.g. `query Foo`, `mutation { ... }`).
     * Not a full parser — intended only for log labeling.
     *
     * @return list<array{name: ?string, type: string}>
     */
    private function parseGraphqlOperations(string $query): array
    {
        $result = [];
        if (preg_match_all('/\b(query|mutation|subscription)\b\s*([A-Za-z_][A-Za-z0-9_]*)?/i', $query, $matches, PREG_SET_ORDER) === false) {
            return $result;
        }
        foreach ($matches as $match) {
            $name = isset($match[2]) && $match[2] !== '' ? $match[2] : null;
            $result[] = [
                'type' => strtolower($match[1]),
                'name' => $name,
            ];
        }
        return $result;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{trace_id: ?string, span_id: ?string, trace_flags: ?string}
     */
    private function extractIncomingTraceContext(Request $request): array
    {
        $traceparent = $this->parseTraceparent($this->firstHeader($request, ['traceparent']));

        $traceId = $this->normalizeHexIdentifier(
            $this->firstHeader($request, ['x-b3-traceid']),
            32
        );
        if ($traceId === null) {
            $traceId = $this->parseAmznTraceId($this->firstHeader($request, ['x-amzn-trace-id']));
        }
        if ($traceId === null) {
            $traceId = $traceparent['trace_id'];
        }

        $spanId = $this->normalizeHexIdentifier(
            $this->firstHeader($request, ['x-b3-spanid']),
            16
        );
        if ($spanId === null) {
            $spanId = $traceparent['span_id'];
        }

        $traceFlags = $this->extractB3TraceFlags($request);
        if ($traceFlags === null) {
            $traceFlags = $traceparent['trace_flags'];
        }

        return [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'trace_flags' => $traceFlags,
        ];
    }

    /**
     * @return array{trace_id: ?string, span_id: ?string, trace_flags: ?string}
     */
    private function parseTraceparent(?string $traceparent): array
    {
        if (!is_string($traceparent) || $traceparent === '') {
            return ['trace_id' => null, 'span_id' => null, 'trace_flags' => null];
        }

        $parts = explode('-', trim($traceparent));
        if (count($parts) !== 4) {
            return ['trace_id' => null, 'span_id' => null, 'trace_flags' => null];
        }

        return [
            'trace_id' => $this->normalizeHexIdentifier($parts[1], 32),
            'span_id' => $this->normalizeHexIdentifier($parts[2], 16),
            'trace_flags' => $this->normalizeHexIdentifier($parts[3], 2, false),
        ];
    }

    private function extractB3TraceFlags(Request $request): ?string
    {
        $flags = $this->firstHeader($request, ['x-b3-flags']);
        if ($flags !== null) {
            $flags = strtolower(trim($flags));
            if ($flags === '1') {
                return '01';
            }
            if ($flags === '0') {
                return '00';
            }
            $normalized = $this->normalizeHexIdentifier($flags, 2, false);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $sampled = $this->firstHeader($request, ['x-b3-sampled']);
        if ($sampled === null) {
            return null;
        }

        $sampled = strtolower(trim($sampled));
        if ($sampled === '1' || $sampled === 'true' || $sampled === 'd') {
            return '01';
        }
        if ($sampled === '0' || $sampled === 'false') {
            return '00';
        }

        return null;
    }

    private function parseAmznTraceId(?string $header): ?string
    {
        if (!is_string($header) || $header === '') {
            return null;
        }

        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            if (stripos($part, 'root=') !== 0) {
                continue;
            }

            $root = substr($part, 5);
            if (!is_string($root)) {
                continue;
            }

            if (preg_match('/^[0-9a-fA-F]+-([0-9a-fA-F]{8})-([0-9a-fA-F]{24})$/', $root, $matches) === 1) {
                return strtolower($matches[1] . $matches[2]);
            }
        }

        return null;
    }

    private function normalizeHexIdentifier(?string $value, int $length, bool $rejectAllZero = true): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '' || preg_match(sprintf('/^[0-9a-f]{%d}$/', $length), $normalized) !== 1) {
            return null;
        }

        if ($rejectAllZero && $normalized === str_repeat('0', $length)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array{trace_id: ?string, span_id: ?string, trace_flags: ?string}
     */
    private function getSpanContext(Request $request): array
    {
        $span = $request->attributes->get(self::SPAN_ATTRIBUTE);
        if (!is_object($span) && class_exists(\OpenTelemetry\API\Trace\Span::class)) {
            $span = \OpenTelemetry\API\Trace\Span::getCurrent();
        }
        if (!is_object($span)) {
            return ['trace_id' => null, 'span_id' => null, 'trace_flags' => null];
        }

        $context = null;
        if (method_exists($span, 'getContext')) {
            $context = $span->getContext();
        } elseif (method_exists($span, 'getSpanContext')) {
            $context = $span->getSpanContext();
        }

        if (!is_object($context)) {
            return ['trace_id' => null, 'span_id' => null, 'trace_flags' => null];
        }
        if (method_exists($context, 'isValid') && !$context->isValid()) {
            return ['trace_id' => null, 'span_id' => null, 'trace_flags' => null];
        }

        $traceId = null;
        if (method_exists($context, 'getTraceId')) {
            $traceId = $context->getTraceId();
        }
        if (!is_string($traceId) || $traceId === '') {
            $traceId = null;
        }

        $spanId = null;
        if (method_exists($context, 'getSpanId')) {
            $spanId = $context->getSpanId();
        }
        if (!is_string($spanId) || $spanId === '') {
            $spanId = null;
        }

        $traceFlags = null;
        if (method_exists($context, 'getTraceFlags')) {
            $traceFlags = $this->normalizeTraceFlags($context->getTraceFlags());
        }

        return [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'trace_flags' => $traceFlags,
        ];
    }

    private function normalizeTraceFlags(mixed $value): ?string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $intValue = (int) $value;
            if ($intValue < 0) {
                return null;
            }

            return str_pad(dechex($intValue & 0xff), 2, '0', STR_PAD_LEFT);
        }

        if (is_string($value)) {
            return $this->normalizeHexIdentifier($value, 2, false);
        }

        if (!is_object($value)) {
            return null;
        }

        if (method_exists($value, 'toByte')) {
            $byte = $value->toByte();
            if (is_int($byte) || is_float($byte) || (is_string($byte) && is_numeric($byte))) {
                return str_pad(dechex(((int) $byte) & 0xff), 2, '0', STR_PAD_LEFT);
            }
        }

        if (method_exists($value, 'isSampled')) {
            $isSampled = $value->isSampled();
            if (is_bool($isSampled)) {
                return $isSampled ? '01' : '00';
            }
        }

        if (method_exists($value, '__toString')) {
            return $this->normalizeHexIdentifier((string) $value, 2, false);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logSlowRequest(array $context): void
    {
        if ($this->slowRequestThresholdMs === null) {
            return;
        }

        $duration = $context['duration_ms'] ?? null;
        if (!is_numeric($duration) || (float) $duration < $this->slowRequestThresholdMs) {
            return;
        }

        $slowContext = $context;
        $slowContext['threshold_ms'] = $this->slowRequestThresholdMs;

        if ($this->logger) {
            $this->logger->warning('slow_request', $slowContext);
            return;
        }

        error_log('slow_request ' . json_encode($slowContext));
    }

    private function readSlowRequestThreshold(string $name): ?float
    {
        $raw = getenv($name);
        if ($raw === false) {
            return self::DEFAULT_SLOW_REQUEST_MS;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return self::DEFAULT_SLOW_REQUEST_MS;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['off', 'false', 'none'], true)) {
            return null;
        }

        if (!is_numeric($raw)) {
            return self::DEFAULT_SLOW_REQUEST_MS;
        }

        $value = (float) $raw;
        return $value > 0 ? $value : null;
    }
}
