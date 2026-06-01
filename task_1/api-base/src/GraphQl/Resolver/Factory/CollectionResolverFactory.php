<?php

declare(strict_types=1);

namespace Ufz\ApiBase\GraphQl\Resolver\Factory;

use ApiPlatform\GraphQl\Resolver\Factory\ResolverFactoryInterface;
use ApiPlatform\GraphQl\Resolver\QueryCollectionResolverInterface;
use ApiPlatform\GraphQl\Resolver\Stage\ReadStageInterface;
use ApiPlatform\GraphQl\Resolver\Stage\SecurityPostDenormalizeStageInterface;
use ApiPlatform\GraphQl\Resolver\Stage\SecurityStageInterface;
use ApiPlatform\GraphQl\Resolver\Stage\SerializeStageInterface;
use ApiPlatform\GraphQl\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

use function json_encode;
use function ksort;

/**
 * Optimized collection resolver for GraphQL relation collections.
 *
 * This keeps API Platform's default behavior and only applies relation shortcuts
 * for explicitly configured relation triples (rootClass, resourceClass, field).
 */
final class CollectionResolverFactory implements ResolverFactoryInterface
{
    private const REQUEST_CACHE_ATTR = '_graphql_optimized_relation_cache';

    public function __construct(
        private readonly ReadStageInterface $readStage,
        private readonly SecurityStageInterface $securityStage,
        private readonly SecurityPostDenormalizeStageInterface $securityPostDenormalizeStage,
        private readonly SerializeStageInterface $serializeStage,
        private readonly ContainerInterface $queryResolverLocator,
        private readonly ?RequestStack $requestStack,
        private readonly ManagerRegistry $managerRegistry,
        private readonly array $optimizedRelations = []
    ) {
    }

    public function __invoke(?string $resourceClass = null, ?string $rootClass = null, ?Operation $operation = null, ?PropertyMetadataFactoryInterface $propertyMetadataFactory = null): callable
    {
        return function (?array $source, array $args, $context, ResolveInfo $info) use ($resourceClass, $rootClass, $operation) {
            if (null === $resourceClass || null === $rootClass) {
                return null;
            }

            if ($this->requestStack && null !== $request = $this->requestStack->getCurrentRequest()) {
                $request->attributes->set(
                    '_graphql_collections_args',
                    [$resourceClass => $args] + $request->attributes->get('_graphql_collections_args', [])
                );
            }

            $resolverContext = [
                'source' => $source,
                'args' => $args,
                'info' => $info,
                'is_collection' => true,
                'is_mutation' => false,
                'is_subscription' => false,
            ];

            $collection = $this->resolveOptimizedRelationCollection($resourceClass, $rootClass, $source, $args, $info);
            if (null === $collection) {
                $collection = ($this->readStage)($resourceClass, $rootClass, $operation, $resolverContext);
            }

            if (!is_iterable($collection)) {
                throw new \LogicException('Collection from read stage should be iterable.');
            }

            if ($operation instanceof Query) {
                $queryResolverId = $operation->getResolver();
                if (null !== $queryResolverId) {
                    /** @var QueryCollectionResolverInterface $queryResolver */
                    $queryResolver = $this->queryResolverLocator->get($queryResolverId);
                    $collection = $queryResolver($collection, $resolverContext);
                }
            }

            ($this->securityStage)($resourceClass, $operation, $resolverContext + [
                'extra_variables' => [
                    'object' => $collection,
                ],
            ]);
            ($this->securityPostDenormalizeStage)($resourceClass, $operation, $resolverContext + [
                'extra_variables' => [
                    'object' => $collection,
                    'previous_object' => is_object($collection) ? clone $collection : $collection,
                ],
            ]);

            return ($this->serializeStage)($collection, $resourceClass, $operation, $resolverContext);
        };
    }

    private function resolveOptimizedRelationCollection(
        string $resourceClass,
        string $rootClass,
        ?array $source,
        array $args,
        ResolveInfo $info
    ): ?iterable {
        if (
            null === $source
            || !isset($source[ItemNormalizer::ITEM_RESOURCE_CLASS_KEY], $source[ItemNormalizer::ITEM_IDENTIFIERS_KEY])
            || $source[ItemNormalizer::ITEM_RESOURCE_CLASS_KEY] !== $rootClass
            || !$this->isOptimizedRelation($rootClass, $resourceClass, $info->fieldName)
            // Keep default API Platform behavior when relation filters/pagination args are present.
            || !empty($args)
        ) {
            return null;
        }

        $manager = $this->managerRegistry->getManagerForClass($rootClass);
        if (null === $manager) {
            return null;
        }

        $metadata = $manager->getClassMetadata($rootClass);
        if (!$metadata->hasAssociation($info->fieldName)) {
            return null;
        }

        $association = $metadata->getAssociationMapping($info->fieldName);
        if (
            ($association['type'] & ClassMetadata::TO_MANY) === 0
            || !$this->isAssociationTargetMatching($association['targetEntity'], $resourceClass)
        ) {
            return null;
        }

        $identifierValues = [];
        foreach ($metadata->identifier as $identifierField) {
            $identifierValue = $source[ItemNormalizer::ITEM_IDENTIFIERS_KEY][$identifierField] ?? null;
            if (null === $identifierValue) {
                return null;
            }
            $identifierValues[$identifierField] = $identifierValue;
        }

        $parentEntity = $manager->getUnitOfWork()->tryGetById($identifierValues, $rootClass);
        if (!is_object($parentEntity)) {
            return null;
        }

        $request = $this->requestStack?->getCurrentRequest();
        $preloadedByParent = $this->getPreloadedChildrenByParent($request, $manager, $metadata, $association, $rootClass, $resourceClass, $info->fieldName);
        if ($preloadedByParent !== null) {
            $identifierHash = $this->buildIdentifierHash($identifierValues);
            return $preloadedByParent[$identifierHash] ?? [];
        }

        return $metadata->getReflectionProperty($info->fieldName)->getValue($parentEntity);
    }

    private function isOptimizedRelation(string $rootClass, string $resourceClass, string $field): bool
    {
        foreach ($this->optimizedRelations as $optimizedRelation) {
            $optimizedRootClass = $optimizedRelation['rootClass'] ?? null;
            $optimizedResourceClass = $optimizedRelation['resourceClass'] ?? null;
            if (
                is_string($optimizedRootClass)
                && is_string($optimizedResourceClass)
                && $this->isAssociationTargetMatching($optimizedRootClass, $rootClass)
                && $this->isAssociationTargetMatching($optimizedResourceClass, $resourceClass)
                && ($optimizedRelation['field'] ?? null) === $field
            ) {
                return true;
            }
        }

        return false;
    }

    private function isAssociationTargetMatching(string $associationTargetEntity, string $resourceClass): bool
    {
        if ($associationTargetEntity === $resourceClass) {
            return true;
        }

        return is_a($resourceClass, $associationTargetEntity, true) || is_a($associationTargetEntity, $resourceClass, true);
    }

    /**
     * @param array<string,mixed> $association
     * @return array<string, array<int, object>>|null
     */
    private function getPreloadedChildrenByParent(
        ?Request $request,
        EntityManagerInterface $manager,
        ClassMetadata $rootMetadata,
        array $association,
        string $rootClass,
        string $resourceClass,
        string $fieldName
    ): ?array {
        if (
            null === $request
            || $association['type'] !== ClassMetadata::ONE_TO_MANY
            || !isset($association['mappedBy'])
            || !is_string($association['mappedBy'])
        ) {
            return null;
        }

        $mappedBy = $association['mappedBy'];
        $cacheKey = $this->getRelationCacheKey($rootClass, $resourceClass, $fieldName);

        /** @var array<string, array<string, array<int, object>>> $cache */
        $cache = $request->attributes->get(self::REQUEST_CACHE_ATTR, []);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $identityMap = $manager->getUnitOfWork()->getIdentityMap();
        /** @var array<int, object> $parents */
        $parents = array_values($identityMap[$rootClass] ?? []);
        if (empty($parents)) {
            $cache[$cacheKey] = [];
            $request->attributes->set(self::REQUEST_CACHE_ATTR, $cache);
            return $cache[$cacheKey];
        }

        $childMetadata = $manager->getClassMetadata($resourceClass);
        if (!$childMetadata->hasAssociation($mappedBy)) {
            return null;
        }

        $qb = $manager->getRepository($resourceClass)->createQueryBuilder('child');
        $qb
            ->andWhere(sprintf('child.%s IN (:parents)', $mappedBy))
            ->setParameter('parents', $parents);

        if (isset($association['orderBy']) && is_array($association['orderBy'])) {
            foreach ($association['orderBy'] as $orderField => $direction) {
                if (!is_string($orderField) || !is_string($direction)) {
                    continue;
                }
                $qb->addOrderBy(sprintf('child.%s', $orderField), $direction);
            }
        }

        $children = $qb->getQuery()->getResult();

        $byParent = [];
        $parentField = $childMetadata->getReflectionProperty($mappedBy);
        foreach ($children as $child) {
            if (!is_object($child)) {
                continue;
            }
            $parent = $parentField->getValue($child);
            if (!is_object($parent) || !$parent instanceof $rootClass) {
                continue;
            }

            $identifierValues = $rootMetadata->getIdentifierValues($parent);
            $identifierHash = $this->buildIdentifierHash($identifierValues);
            $byParent[$identifierHash] ??= [];
            $byParent[$identifierHash][] = $child;
        }

        $cache[$cacheKey] = $byParent;
        $request->attributes->set(self::REQUEST_CACHE_ATTR, $cache);

        return $cache[$cacheKey];
    }

    private function getRelationCacheKey(string $rootClass, string $resourceClass, string $fieldName): string
    {
        return sprintf('%s::%s::%s', $rootClass, $resourceClass, $fieldName);
    }

    /**
     * @param array<string,mixed> $identifierValues
     */
    private function buildIdentifierHash(array $identifierValues): string
    {
        ksort($identifierValues);
        return (string) json_encode($identifierValues);
    }
}
