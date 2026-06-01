<?php

namespace Ufz\ApiBase\Telemetry;

use Psr\Log\LoggerInterface;

final class TracerFactory
{
    private static bool $initialized = false;
    private static ?object $tracer = null;
    private static ?object $provider = null;
    private static bool $logNoTracer = false;

    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function getTracer(): ?object
    {
        if (self::$tracer !== null || self::$initialized) {
            return self::$tracer;
        }

        self::$initialized = true;
        if ($this->isSdkDisabled()) {
            $this->logOnce(sprintf(
                'OpenTelemetry SDK disabled for this environment; tracing is off. OTEL_SDK_DISABLED=%s APP_ENV=%s',
                var_export(getenv('OTEL_SDK_DISABLED'), true),
                var_export(getenv('APP_ENV'), true),
            ));
            return null;
        }

        if (!class_exists(\OpenTelemetry\API\Globals::class) || !class_exists(\OpenTelemetry\SDK\Trace\TracerProviderFactory::class)) {
            $this->logOnce('OpenTelemetry SDK classes not available; tracing is off.');
            return null;
        }

        $this->initializeProvider();

        if (self::$provider !== null && method_exists(self::$provider, 'getTracer')) {
            self::$tracer = self::$provider->getTracer('ufz.api-base');
            return self::$tracer;
        }

        self::$tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('ufz.api-base');

        return self::$tracer;
    }

    private function initializeProvider(): void
    {
        if (!class_exists(\OpenTelemetry\SDK\Trace\TracerProviderFactory::class)) {
            return;
        }

        try {
            $provider = (new \OpenTelemetry\SDK\Trace\TracerProviderFactory())->create();
        } catch (\Throwable $exception) {
            $this->logOnce(sprintf(
                'OpenTelemetry tracer provider init failed: %s',
                $exception->getMessage()
            ));
            return;
        }

        self::$provider = $provider;

        if (method_exists(\OpenTelemetry\API\Globals::class, 'setTracerProvider')) {
            \OpenTelemetry\API\Globals::setTracerProvider($provider);
        }

        if (method_exists($provider, 'shutdown')) {
            register_shutdown_function(static function () use ($provider): void {
                $provider->shutdown();
            });
        }
    }

    private function isSdkDisabled(): bool
    {
        $value = getenv('OTEL_SDK_DISABLED');
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return $value === 'true' || $value === '1' || $value === 'yes';
        }

        $appEnv = getenv('APP_ENV');
        if (!is_string($appEnv)) {
            return false;
        }

        return strtolower(trim($appEnv)) === 'test';
    }

    private function logOnce(string $message): void
    {
        if (self::$logNoTracer) {
            return;
        }

        self::$logNoTracer = true;
        if ($this->logger) {
            $this->logger->warning($message);
            return;
        }

        error_log($message);
    }
}
