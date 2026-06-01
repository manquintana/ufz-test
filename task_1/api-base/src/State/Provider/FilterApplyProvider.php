<?php declare(strict_types=1);

namespace Ufz\ApiBase\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * Provider for the Filter 'apply' GraphQL operation.
 * Returns an empty array — the FilterApplyResolver handles all data retrieval.
 */
class FilterApplyProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return [];
    }
}
