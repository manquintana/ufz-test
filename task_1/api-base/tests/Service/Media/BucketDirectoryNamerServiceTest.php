<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;
use Ufz\ApiBase\Service\Media\BucketDirectoryNamerService;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class BucketDirectoryNamerServiceTest extends TestCase
{
    public function testDirectoryName()
    {
        $directory = 'aDirectoryName';

        $service = new BucketDirectoryNamerService();
        $service->setDirectory($directory);
        $propertyMapping = new PropertyMapping('aFileProperty', 'aFileNameProperty');

        $result = $service->directoryName(new MediaItem(), $propertyMapping);

        self::assertEquals($directory, $result);
        self::assertEquals($directory, $service->getDirectory());
    }
}
