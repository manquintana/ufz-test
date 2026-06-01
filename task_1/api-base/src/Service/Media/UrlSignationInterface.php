<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

interface UrlSignationInterface
{
    /**
     * Generate a signed Url to access an object identified by $url.
     */
    public function __invoke(string $url): string;
}
