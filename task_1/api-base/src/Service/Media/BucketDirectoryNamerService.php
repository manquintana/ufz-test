<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

/**
 * Used by vich uploader to fetch the target directory when uploading file to object store.
 *
 * @package Ufz\ApiBase\Service\Media
 */
class BucketDirectoryNamerService implements DirectoryNamerInterface
{
    protected string $directory = '';

    public function directoryName($object, PropertyMapping $mapping): string
    {
        return $this->directory;
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * @param string $directory
     * @return BucketDirectoryNamerService
     */
    public function setDirectory(string $directory): BucketDirectoryNamerService
    {
        $this->directory = $directory;
        return $this;
    }
}
