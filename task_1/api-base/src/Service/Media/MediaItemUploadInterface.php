<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;

/**
 * Interface to be implemented by media upload services.
 * @package Ufz\ApiBase\Service\Media
 */
interface MediaItemUploadInterface
{
    /**
     * Upload a MediaItem and return the url to access the uploaded file.
     * @param MediaItem $item
     * @return string
     */
    public function upload(MediaItem $item): string;

    /**
     * Get the base storage URL used by this upload service (with name of bucket).
     * @return string
     */
    public function getStorageUrl(): string;

}
