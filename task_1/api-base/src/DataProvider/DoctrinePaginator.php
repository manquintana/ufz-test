<?php

namespace Ufz\ApiBase\DataProvider;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Traversable;

/**
 * Paginator adapter to more conveniently use the doctrine paginator object
 *
 * @template T
 */
class DoctrinePaginator
{
  /**
   * Constructor
   *
   * @param Paginator<T> $paginator
   */
  public function __construct(protected Paginator $paginator)
  {
  }

  /**
   * @param Paginator<T> $paginator
   */
  public function setPaginator(Paginator $paginator): static
  {
    $this->paginator = $paginator;
    return $this;
  }

  /**
   * @return Paginator<T>
   */
  public function getPaginator(): Paginator
  {
    return $this->paginator;
  }

  /**
   * Returns a collection of items for a page.
   *
   * @param int $offset
   * @param int $itemCountPerPage
   * @return Traversable<int, T>
   * @throws Exception
   */
  public function getItems(int $offset, int $itemCountPerPage): Traversable
  {
    $this->paginator->getQuery()
      ->setFirstResult($offset)
      ->setMaxResults($itemCountPerPage)
    ;

    return $this->paginator->getIterator();
  }

  public function count(): int
  {
    return $this->paginator->count();
  }
}