<?php

namespace Ufz\ApiBase\Telemetry\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use Psr\Log\LoggerInterface;
use Ufz\ApiBase\Telemetry\TracerFactory;

use function array_slice;
use function func_get_args;
use function func_num_args;

final class TracingStatement extends AbstractStatementMiddleware
{
    private TracerFactory $tracerFactory;
    private ?LoggerInterface $logger;
    private ?float $slowQueryThresholdMs;
    /** @var array<string, mixed> */
    private array $connectionAttributes;
    private string $sql;

    /** @var array<int, mixed>|array<string, mixed> */
    private array $params = [];

    /** @var array<int, int>|array<string, int> */
    private array $types = [];

    /**
     * @param array<string, mixed> $connectionAttributes
     */
    public function __construct(
        StatementInterface $statement,
        TracerFactory $tracerFactory,
        ?LoggerInterface $logger,
        ?float $slowQueryThresholdMs,
        array $connectionAttributes,
        string $sql
    ) {
        parent::__construct($statement);
        $this->tracerFactory = $tracerFactory;
        $this->logger = $logger;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
        $this->connectionAttributes = $connectionAttributes;
        $this->sql = $sql;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@see bindValue()} instead.
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                    . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        $this->params[$param] = &$variable;
        $this->types[$param]  = $type;

        return parent::bindParam($param, $variable, $type, ...array_slice(func_get_args(), 3));
    }

    /**
     * {@inheritDoc}
     * @return bool
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindValue() is deprecated.'
                    . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        $this->params[$param] = $value;
        $this->types[$param]  = $type;

        return parent::bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($params = null): ResultInterface
    {
        $span = SpanHelper::startSpan($this->tracerFactory, $this->sql, $this->connectionAttributes);
        $start = microtime(true);

        try {
            return parent::execute($params);
        } catch (\Throwable $exception) {
            SpanHelper::recordException($span, $exception);
            throw $exception;
        } finally {
            $durationMs = (microtime(true) - $start) * 1000;
            SpanHelper::endSpan($span, $durationMs);
            $paramsCount = $params !== null ? count((array) $params) : count($this->params);
            $this->logSlowQuery($durationMs, $paramsCount);
        }
    }

    private function logSlowQuery(float $durationMs, int $paramsCount): void
    {
        if ($this->slowQueryThresholdMs === null || $durationMs < $this->slowQueryThresholdMs) {
            return;
        }

        $context = [
            'duration_ms' => round($durationMs, 2),
            'sql' => $this->sql,
            'params_count' => $paramsCount,
        ];

        if ($this->logger) {
            $this->logger->warning('slow_db_query', $context);
            return;
        }

        error_log('slow_db_query ' . json_encode($context));
    }
}
