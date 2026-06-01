<?php

namespace Ufz\ApiBase\Telemetry\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Ufz\ApiBase\Telemetry\TracerFactory;

final class DbalTracingMiddleware implements MiddlewareInterface
{
    private const DEFAULT_SLOW_QUERY_MS = 500.0;

    private TracerFactory $tracerFactory;
    private ?LoggerInterface $logger;
    private ?float $slowQueryThresholdMs;

    public function __construct(TracerFactory $tracerFactory, ?LoggerInterface $logger = null)
    {
        $this->tracerFactory = $tracerFactory;
        $this->logger = $logger;
        $this->slowQueryThresholdMs = $this->readSlowQueryThreshold('UFZ_SLOW_DB_QUERY_MS');
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new TracingDriver(
            $driver,
            $this->tracerFactory,
            $this->logger,
            $this->slowQueryThresholdMs
        );
    }

    private function readSlowQueryThreshold(string $name): ?float
    {
        $raw = getenv($name);
        if ($raw === false) {
            return self::DEFAULT_SLOW_QUERY_MS;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return self::DEFAULT_SLOW_QUERY_MS;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['off', 'false', 'none'], true)) {
            return null;
        }

        if (!is_numeric($raw)) {
            return self::DEFAULT_SLOW_QUERY_MS;
        }

        $value = (float) $raw;
        return $value > 0 ? $value : null;
    }
}
