<?php


namespace Ufz\ApiBase\Tests\Service\Media;

use Ufz\ApiBase\Service\Media\SupportedFilesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class SupportedFilesServiceTest extends TestCase
{

    const NAME = 'name';
    const ENDING = 'jpg';
    const UPPERCASE_ENDING = 'JPG';

    public function testSupportedFile()
    {
        $file1 = self::createMock(File::class);
        $file1->expects(self::exactly(3))->method('guessExtension')->willReturn(self::ENDING);

        $file2 = self::createMock(File::class);
        $file2->expects(self::exactly(3))->method('guessExtension')->willReturn(self::UPPERCASE_ENDING);


        $service1 = new SupportedFilesService([self::ENDING]);
        $service2 = new SupportedFilesService(['png', self::ENDING]);
        $service3 = new SupportedFilesService([self::UPPERCASE_ENDING]);

        self::assertTrue($service1->isSupported($file1));
        self::assertTrue($service1->isSupported($file2));

        self::assertTrue($service2->isSupported($file1));
        self::assertTrue($service2->isSupported($file2));

        self::assertTrue($service3->isSupported($file1));
        self::assertTrue($service3->isSupported($file2));
    }

    public function testUnsupportedFile()
    {
        $file = self::createMock(File::class);
        $file->expects(self::exactly(2))->method('guessExtension')->willReturn(self::ENDING);

        $service1 = new SupportedFilesService([]);
        $service2 = new SupportedFilesService(['png']);

        self::assertFalse($service1->isSupported($file));
        self::assertFalse($service2->isSupported($file));
    }

    public function testEmptyFileEnding()
    {
        $file = self::createMock(File::class);
        $file->expects(self::exactly(2))->method('guessExtension')->willReturn('');

        $service1 = new SupportedFilesService([]);
        $service2 = new SupportedFilesService(['']);

        self::assertFalse($service1->isSupported($file));
        self::assertTrue($service2->isSupported($file));
    }

}
