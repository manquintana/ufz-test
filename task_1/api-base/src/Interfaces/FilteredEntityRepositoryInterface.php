<?php

namespace Ufz\ApiBase\Interfaces;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository in main project that provides business entities must implement this interface
 * and provide query builder and additional criteria, that might me out of the scope of filtering by conditions
 * specified in FilterHistory.
 *
 * Example: query builder might contain relations in business domain
 * and additional filtering by logged in user might be required.
 *
 * @package Ufz\ApiBase\Tests\Entity
 */
interface FilteredEntityRepositoryInterface
{
    /**
     * Provide query builder from main project and possibly required relations.
     *
     * @return QueryBuilder
     */
    public function getFilteredEntityQueryBuilder(): QueryBuilder;

    /**
     * Provide additional criteria from main project, to be applied to the query builder at the end.
     * ie: filter by logged in user should be provided here and it will be applied on query builder.
     *
     * @return Criteria
     */
    public function addAdditionalCriteria(): Criteria;
}
