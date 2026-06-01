<?php

namespace Ufz\ApiBase\DataProvider;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DoctrinePaginatorFactory
{
    public function getPaginator(AbstractQuery $query): DoctrinePaginator
    {
        return new DoctrinePaginator(new Paginator($query));
    }
}
