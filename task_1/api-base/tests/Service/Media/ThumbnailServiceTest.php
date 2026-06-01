<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Ufz\ApiBase\DTO\MediaItem;
use Ufz\ApiBase\Service\Media\ThumbnailService;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\TestCase;

class ThumbnailServiceTest extends TestCase
{

    const SUFFIX_SEPARATOR = '_';

    public function testGenerate()
    {
        try {
            $originalName = 'input.jpg';
            $inputFilepath = '/tmp/input.jpg';
            $imagine = new Imagine();

            $width0 = 100;
            $height0 = 100;
            $inputImage = $imagine->create(new Box($width0, $height0));
            $inputImage->save($inputFilepath);

            $inputFile = new UploadedFile($inputFilepath, $originalName, 'image/jpg');

            $inputItem = new MediaItem();
            $inputItem->setFile($inputFile);
            $inputItem->setName($inputFilepath);

            $suffix1 = 'foo';
            $width1 = 50;
            $suffix2 = 'bar';
            $width2 = 30;

            $thumbnailSizes = [[$suffix1, $width1], [$suffix2, $width2]];

            $service = new ThumbnailService($imagine, self::SUFFIX_SEPARATOR, $thumbnailSizes);

            $thumbnails = $service->generate($inputItem);

            self::assertNotEmpty($thumbnails);

            array_map(function (array $thumbnailWithLabel, array $size) use ($imagine) {
                list($expSuffix, $expWidth) = $size;
                [$label, $thumbnail] = $thumbnailWithLabel;

                $filename = (pathinfo($thumbnail->getFile()->getFilename(), PATHINFO_FILENAME));
                self::assertStringEndsWith(self::SUFFIX_SEPARATOR . $expSuffix, $filename);
                self::assertEquals($expSuffix, $label);

                $image = $imagine->open($thumbnail->getFile());
                $imageSize = $image->getSize();

                self::assertEquals($expWidth, $imageSize->getWidth());
                self::assertEquals($expWidth, $imageSize->getHeight());

            }, $thumbnails, $thumbnailSizes);

        } finally {
            if (file_exists($inputFilepath)) {
                unlink($inputFilepath);;
            }
        }
    }
}
