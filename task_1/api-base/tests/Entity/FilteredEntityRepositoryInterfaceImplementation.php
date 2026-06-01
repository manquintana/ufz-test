<?php

namespace Ufz\ApiBase\Tests\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Ufz\ApiBase\Interfaces\FilteredEntityRepositoryInterface;

class FilteredEntityRepositoryInterfaceImplementation extends ServiceEntityRepository implements FilteredEntityRepositoryInterface
{
    public function getFilteredEntityQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder();
    }

    public function addAdditionalCriteria(): Criteria
    {
        return Criteria::create();
    }
}