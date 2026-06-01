<?php

namespace Ufz\ApiBase\Interfaces;

/**
 * Class in main project that is in charge of persisting the image and thumbnail needs to implement this interface.
 */
interface UploadImagePersisterInterface
{
    /**
     * find images by ref
     * @param string $ref ref of the image to find
     * @return array
     */
    public function getImageRefByRef(string $ref): array;

    /**
     * @param string $ref
     * @param $size
     * @param bool $isPublic
     * @return mixed
     */
    public function persistImage(string $ref, $size, bool $isPublic = false);

    /**
     * @param string $ref
     * @param string $label
     * @param string $signedUrl
     * @param $size
     * @param $imageReference
     * @return mixed
     */
    public function persistThumbnail(string $ref, string $label, string $signedUrl, $size, $imageReference);

    /**
     * @param int $id
     * @return mixed | null
     */
    public function getImageRefById(int $id);

    /**
     * Remove an image by its ID
     *
     * @param int $id ID of the image to remove
     * @return bool true if the image was removed successfully, false otherwise
     */
    public function removeImageById(int $id): bool;

    /**
     * Remove image(s) by its ref
     *
     * @param string $ref ref of the image to remove
     * @return bool true if the image was removed successfully, false otherwise
     */
    public function removeImageByRef(string $ref): bool;
}
