<?php

namespace Ufz\ApiBase\Service\Media;

final class StorageUrlBuilder
{
    public function __construct(
        private string $publicStorageUrl,
        private string $restrictedStorageUrl
    ) {}

    /**
     * Builds a full storage URL for the given file in the appropriate bucket.
     *
     * @param bool $isPublic
     * @param string $file
     * @return string
     */
    public function build(bool $isPublic, string $file): string
    {
        $bucket = $isPublic ? $this->publicStorageUrl : $this->restrictedStorageUrl;
        return rtrim($bucket, '/') . '/' . ltrim($file, '/');
    }

    /**
     * Strips the storage URL prefix from the given URL, returning only the file path.
     * If the URL does not start with either storage URL, it is returned unchanged.
     *
     * @param string $maybeUrl
     * @return string
     */
    public function strip(string $maybeUrl): string
    {
        if (str_starts_with($maybeUrl, $this->publicStorageUrl)) {
            return substr($maybeUrl, strlen($this->publicStorageUrl));
        }
        if (str_starts_with($maybeUrl, $this->restrictedStorageUrl)) {
            return substr($maybeUrl, strlen($this->restrictedStorageUrl));
        }
        return $maybeUrl;
    }
}