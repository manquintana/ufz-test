<?php declare(strict_types=1);

namespace Ufz\ApiBase\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * No-op state provider for DTO resources.
 * Returns an empty array — the actual data is provided by custom GraphQL resolvers.
 */
class NoOpProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return [];
    }
}
