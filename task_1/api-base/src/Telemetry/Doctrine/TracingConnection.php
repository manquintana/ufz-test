<?php

namespace Ufz\ApiBase\Telemetry\Doctrine;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Psr\Log\LoggerInterface;
use Ufz\ApiBase\Telemetry\TracerFactory;

final class TracingConnection extends AbstractConnectionMiddleware
{
    private TracerFactory $tracerFactory;
    private ?LoggerInterface $logger;
    private ?float $slowQueryThresholdMs;
    /** @var array<string, mixed> */
    private array $connectionAttributes;

    /**
     * @param array<string, mixed> $connectionAttributes
     */
    public function __construct(
        ConnectionInterface $connection,
        TracerFactory $tracerFactory,
        ?LoggerInterface $logger,
        ?float $slowQueryThresholdMs,
        array $connectionAttributes
    ) {
        parent::__construct($connection);
        $this->tracerFactory = $tracerFactory;
        $this->logger = $logger;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
        $this->connectionAttributes = $connectionAttributes;
    }

    public function prepare(string $sql): DriverStatement
    {
        return new TracingStatement(
            parent::prepare($sql),
            $this->tracerFactory,
            $this->logger,
            $this->slowQueryThresholdMs,
            $this->connectionAttributes,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        return $this->executeWithSpan($sql, function () use ($sql): Result {
            return parent::query($sql);
        });
    }

    public function exec(string $sql): int
    {
        return $this->executeWithSpan($sql, function () use ($sql): int {
            return parent::exec($sql);
        });
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $execute
     *
     * @return TResult
     */
    private function executeWithSpan(string $sql, callable $execute)
    {
        $span = SpanHelper::startSpan($this->tracerFactory, $sql, $this->connectionAttributes);
        $start = microtime(true);

        try {
            return $execute();
        } catch (\Throwable $exception) {
            SpanHelper::recordException($span, $exception);
            throw $exception;
        } finally {
            $durationMs = (microtime(true) - $start) * 1000;
            SpanHelper::endSpan($span, $durationMs);
            $this->logSlowQuery($sql, $durationMs, null);
        }
    }

    private function logSlowQuery(string $sql, float $durationMs, ?int $paramsCount): void
    {
        if ($this->slowQueryThresholdMs === null || $durationMs < $this->slowQueryThresholdMs) {
            return;
        }

        $context = [
            'duration_ms' => round($durationMs, 2),
            'sql' => $sql,
        ];
        if ($paramsCount !== null) {
            $context['params_count'] = $paramsCount;
        }

        if ($this->logger) {
            $this->logger->warning('slow_db_query', $context);
            return;
        }

        error_log('slow_db_query ' . json_encode($context));
    }
}
