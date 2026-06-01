<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\BucketDirectoryNamerService;
use Ufz\ApiBase\Service\Media\SupportedFilesInterface;
use Ufz\ApiBase\Service\Media\ThumbnailInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AbstractUploadServiceTest extends TestCase
{
    private ?MockObject $supportedFilesService;
    private ?MockObject $thumbnailService;
    private ?MockObject $directoryNamerService;
    private ?MockObject $uploadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supportedFilesService = $this->createMock(SupportedFilesInterface::class);
        $this->thumbnailService = $this->createMock(ThumbnailInterface::class);
        $this->directoryNamerService = $this->createMock(BucketDirectoryNamerService::class);

        $this->uploadService = self::getMockForAbstractClass(
            AbstractUploadService::class,
            [
                $this->supportedFilesService,
                $this->thumbnailService,
                $this->directoryNamerService
            ]
        );
    }

    public function testInvokeWithEmptyFiles()
    {
        self::assertEmpty(($this->uploadService)(array()));
    }

    public function testInvokeUnsupportedFile()
    {
        $name = 'name';
        $file = self::createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($name);

        $this->supportedFilesService->method('isSupported')->willReturn(false);

        self::expectException(BadRequestHttpException::class);
        self::expectExceptionMessage(AbstractUploadService::ERROR_UNSUPPORTED_FILES . $name . ' ');
        ($this->uploadService)([$file]);
    }

    public function testInvokeImage()
    {
        $name = 'name';
        $file = self::createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($name);
        $file->method('getMimeType')->willReturn(AbstractUploadService::IMAGE_MIME_PREFIX);

        $this->supportedFilesService->method('isSupported')->willReturn(true);
        $thumbnails = [['thumbnailLabel', new MediaItem()]];
        $this->thumbnailService->expects(self::once())->method('generate')->willReturn($thumbnails);

        $expected = ['a', 'b'];
        $this->uploadService->expects(self::once())->method('uploadMedia')->willReturn($expected);

        $result = ($this->uploadService)([$file]);

        self::assertEquals($expected, $result);
    }

    public function testInvokeDocument()
    {
        $name = 'name';
        $file = self::createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($name);
        $file->method('getMimeType')->willReturn('pdf');

        $this->supportedFilesService->method('isSupported')->willReturn(true);
        $this->thumbnailService->expects(self::never())->method('generate');

        $expected = ['a', 'b'];
        $this->uploadService->expects(self::once())->method('uploadMedia')->willReturn($expected);

        $result = ($this->uploadService)([$file]);

        self::assertEquals($expected, $result);
    }

    /**
     * @dataProvider filenameProvider
     */
    public function testReplaceSpecialChars($input, $output)
    {
        self::assertEquals($output, $this->uploadService->replaceSpecialChars($input));
    }

    /**
     * Provide test file names as well as expected results to run test once for each entry.
     *
     * @return string[][]
     */
    public function filenameProvider(): array
    {
        return array(
            array('file_name_test_1.jpg', 'file_name_test_1.jpg'),
            array('file name test 1.jpg', 'file_name_test_1.jpg'),
            array('file%name+test 1.jpg', 'file_name_test_1.jpg'),
            array('file.name.test.1.jpg', 'file_name_test_1.jpg')
        );
    }
}
