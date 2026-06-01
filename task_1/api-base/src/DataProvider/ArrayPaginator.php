<?php

namespace Ufz\ApiBase\DataProvider;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * CustomPaginator is a simple implementation of PaginatorInterface
 * that allows for pagination of an array of results and return it for
 * api platform GraphQL queries.
 */
class ArrayPaginator implements PaginatorInterface, \IteratorAggregate
{
    private array $results;
    private int $firstResult;
    private int $itemsPerPage;
    private int $totalItems;

    public function __construct(array $results, int $firstResult, int $itemsPerPage, int $totalItems)
    {
        $this->results = $results;
        $this->firstResult = $firstResult;
        $this->itemsPerPage = $itemsPerPage;
        $this->totalItems = $totalItems;
    }

    public function getCurrentPage(): float
    {
        return $this->itemsPerPage > 0 ? floor($this->firstResult / $this->itemsPerPage) + 1 : 1.;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getLastPage(): float
    {
        if ($this->itemsPerPage <= 0) {
            return 1.;
        }
        return ceil($this->totalItems / $this->itemsPerPage) ?: 1.;
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function count(): int
    {
        return \count($this->results);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->results);
    }
}
