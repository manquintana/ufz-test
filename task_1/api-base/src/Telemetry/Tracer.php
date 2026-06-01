<?php

declare(strict_types=1);

namespace Ufz\ApiBase\Telemetry;

final class Tracer
{
    private static ?TracerFactory $factory = null;

    public static function setFactory(TracerFactory $factory): void
    {
        self::$factory = $factory;
    }

    /**
     * Wrap any callable in a traced span.
     *
     * @template T
     *
     * @param string                $name       Span name (e.g. "s3.upload", "thumbnail.generate")
     * @param callable(): T         $callable   The work to trace
     * @param array<string, mixed>  $attributes Extra span attributes
     *
     * @return T
     */
    public static function span(string $name, callable $callable, array $attributes = []): mixed
    {
        $tracer = self::$factory?->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            return $callable();
        }

        $builder = $tracer->spanBuilder($name);
        if (class_exists(\OpenTelemetry\API\Trace\SpanKind::class) && method_exists($builder, 'setSpanKind')) {
            $builder->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL);
        }

        $span = $builder->startSpan();
        $scope = method_exists($span, 'activate') ? $span->activate() : null;

        if (method_exists($span, 'setAttribute')) {
            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }

        try {
            $result = $callable();

            if (method_exists($span, 'end')) {
                $span->end();
            }

            return $result;
        } catch (\Throwable $e) {
            if (method_exists($span, 'recordException')) {
                $span->recordException($e);
            }
            if (method_exists($span, 'setStatus') && class_exists(\OpenTelemetry\API\Trace\StatusCode::class)) {
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
            }
            if (method_exists($span, 'end')) {
                $span->end();
            }

            throw $e;
        } finally {
            if ($scope !== null && method_exists($scope, 'detach')) {
                $scope->detach();
            }
        }
    }
}
