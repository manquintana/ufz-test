<?php

namespace Ufz\ApiBase\Service\Media;

class IsMediaDeletableService implements IsMediaDeletableInterface
{

    public function __invoke(string $path): bool
    {
        // only allow files directly beneath /uploads, not in nested directories
        $isFile = !str_ends_with($path, '/');
        $isInUploadsDirectory = str_starts_with($path, AbstractUploadService::UPLOADS_FOLDER);
        $isInNestedDirectory = dirname($path) != AbstractUploadService::UPLOADS_FOLDER;

        return $isFile && $isInUploadsDirectory && !$isInNestedDirectory;
    }
}
