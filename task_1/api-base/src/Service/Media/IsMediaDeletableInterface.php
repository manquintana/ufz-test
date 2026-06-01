<?php


namespace Ufz\ApiBase\Service\Media;


interface IsMediaDeletableInterface
{

    /**
     * Returns whether if a file at $path is deletable.
     */
    public function __invoke(string $path): bool;

}
