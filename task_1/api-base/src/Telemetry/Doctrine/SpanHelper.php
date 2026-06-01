<?php

namespace Ufz\ApiBase\Telemetry\Doctrine;

use Ufz\ApiBase\Telemetry\TracerFactory;

final class SpanHelper
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $connectionAttributes
     */
    public static function startSpan(TracerFactory $tracerFactory, string $sql, array $connectionAttributes): ?object
    {
        $tracer = $tracerFactory->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            return null;
        }

        $operation = self::getOperationName($sql);
        $spanName = $operation !== null ? sprintf('DB %s', $operation) : 'DB query';

        $builder = $tracer->spanBuilder($spanName);
        if (class_exists(\OpenTelemetry\API\Trace\SpanKind::class) && method_exists($builder, 'setSpanKind')) {
            $builder->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CLIENT);
        }

        $span = $builder->startSpan();
        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('db.statement', $sql);
            if ($operation !== null) {
                $span->setAttribute('db.operation', $operation);
            }
            foreach ($connectionAttributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }

        return $span;
    }

    public static function endSpan(?object $span, float $durationMs): void
    {
        if (!is_object($span)) {
            return;
        }

        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('db.duration_ms', round($durationMs, 2));
        }

        if (method_exists($span, 'end')) {
            $span->end();
        }
    }

    public static function recordException(?object $span, \Throwable $exception): void
    {
        if (!is_object($span)) {
            return;
        }

        if (method_exists($span, 'recordException')) {
            $span->recordException($exception);
        }

        if (method_exists($span, 'setStatus') && class_exists(\OpenTelemetry\API\Trace\StatusCode::class)) {
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $exception->getMessage());
        }
    }

    private static function getOperationName(string $sql): ?string
    {
        $trimmed = ltrim($sql);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^([A-Za-z]+)/', $trimmed, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
