<?php

namespace Ufz\ApiBase\Interfaces;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Interface to implement when introducing new external filter (additional filter that is not in api-base package).
 * Example: TaxonomyFilter in TMD project.
 */
interface ExternalFilterInterface
{
    /**
     * Checks if the specified filter name is supported by this filter.
     *
     * @param string $filterName
     * @return bool
     */
    public function supports(string $filterName): bool;

    /**
     * Return array of filter data that is applicable for filtering in GenericFieldBasedFilter.
     *
     * @param string $resourceClass
     * @return array
     */
    public function getFilters(string $resourceClass): array;

    /**
     * Apply actual filtering. In cases of implementing AbstractFilter, this method is just a
     * wrapper for calling protected method filterProperty()
     *
     * @param string $property
     * @param string|array $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @return mixed
     */
    public function doFiltering(
        string $property, 
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass
    );
}
