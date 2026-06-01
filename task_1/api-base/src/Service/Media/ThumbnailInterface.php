<?php


namespace Ufz\ApiBase\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;

interface ThumbnailInterface
{

    /**
     * Generate thumbnails for an image.
     * @param MediaItem $file input
     * @return array<array<string, MediaItem>> labeled thumbnails
     */
    public function generate(MediaItem $file): array;

}
