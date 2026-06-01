<?php


namespace Ufz\ApiBase\Service\Media;


interface MediaDeletionInterface
{

    /**
     * Delete a single file and its optional thumbnails.
     *
     * @param string $path
     */
    public function delete(string $path): void;

    /**
     * Delete multiple files. Throws if a file is not found.
     *
     * @param array $paths paths to files
     */
    public function deleteMany(array $paths): void;

}
