<?php

namespace Ufz\ApiBase\Telemetry\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Ufz\ApiBase\Telemetry\TracerFactory;

final class TracingDriver extends AbstractDriverMiddleware
{
    private TracerFactory $tracerFactory;
    private ?LoggerInterface $logger;
    private ?float $slowQueryThresholdMs;

    public function __construct(
        DriverInterface $driver,
        TracerFactory $tracerFactory,
        ?LoggerInterface $logger,
        ?float $slowQueryThresholdMs
    ) {
        parent::__construct($driver);
        $this->tracerFactory = $tracerFactory;
        $this->logger = $logger;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
    }

    /**
     * @return \Doctrine\DBAL\Driver\Connection
     */
    public function connect(
        #[SensitiveParameter]
        array $params
    ) {
        $connection = parent::connect($params);

        return new TracingConnection(
            $connection,
            $this->tracerFactory,
            $this->logger,
            $this->slowQueryThresholdMs,
            $this->buildConnectionAttributes($params)
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildConnectionAttributes(array $params): array
    {
        $attributes = [];
        $driver = (string) ($params['driver'] ?? '');
        $url = (string) ($params['url'] ?? '');

        $dbSystem = null;
        if (stripos($driver, 'pgsql') !== false || stripos($url, 'postgres') !== false) {
            $dbSystem = 'postgresql';
        } elseif (stripos($driver, 'mysql') !== false || stripos($url, 'mysql') !== false) {
            $dbSystem = 'mysql';
        } elseif (stripos($driver, 'sqlite') !== false || stripos($url, 'sqlite') !== false) {
            $dbSystem = 'sqlite';
        }

        if ($dbSystem !== null) {
            $attributes['db.system'] = $dbSystem;
        }

        $dbName = $params['dbname'] ?? null;
        if (!is_string($dbName) && is_string($url)) {
            $parsed = parse_url($url);
            if (is_array($parsed)) {
                $path = $parsed['path'] ?? null;
                if (is_string($path) && $path !== '') {
                    $dbName = ltrim($path, '/');
                }
            }
        }
        if (is_string($dbName) && $dbName !== '') {
            $attributes['db.name'] = $dbName;
        }

        $host = $params['host'] ?? null;
        if (is_string($host) && $host !== '') {
            $attributes['net.peer.name'] = $host;
        }

        $port = $params['port'] ?? null;
        if (is_int($port) || (is_string($port) && ctype_digit($port))) {
            $attributes['net.peer.port'] = (int) $port;
        }

        return $attributes;
    }
}
