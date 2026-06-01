<?php


namespace Ufz\ApiBase\Tests\Service\Media;


use Ufz\ApiBase\Service\Media\ThumbnailNamingService;
use PHPUnit\Framework\TestCase;

class ThumbnailNamingServiceTest extends TestCase
{

    const SUFFIX_SEPARATOR = '_';

    public function testThumbnailForFile(): void
    {
        $filename1 = 'filename';
        $filename2 = 'filename.ext';
        $suffix = 'suffix';

        $service = new ThumbnailNamingService(self::SUFFIX_SEPARATOR, []);

        self::assertEquals('filename_suffix', $service->thumbnailForFile($filename1, $suffix));
        self::assertEquals('filename_suffix.ext', $service->thumbnailForFile($filename2, $suffix));
    }

    public function testThumbnailsForFile(): void
    {
        $filename1 = 'filename';
        $filename2 = 'filename.ext';

        $service = new ThumbnailNamingService(self::SUFFIX_SEPARATOR, []);
        self::assertEmpty($service->thumbnailsForFile($filename1));

        $sizes = [['medium', 123], ['small', 321]];
        $service = new ThumbnailNamingService(self::SUFFIX_SEPARATOR, $sizes);

        $expected1 = ['filename_medium', 'filename_small'];
        $expected2 = ['filename_medium.ext', 'filename_small.ext'];

        self::assertEquals($expected1, $service->thumbnailsForFile($filename1));
        self::assertEquals($expected2, $service->thumbnailsForFile($filename2));
    }
}
