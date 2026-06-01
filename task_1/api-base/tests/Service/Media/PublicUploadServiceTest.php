<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use Doctrine\ORM\EntityManagerInterface;
use Ufz\ApiBase\DTO\MediaItem;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\BucketDirectoryNamerService;
use Ufz\ApiBase\Service\Media\MediaItemUploadInterface;
use Ufz\ApiBase\Service\Media\PublicUploadService;
use Ufz\ApiBase\Service\Media\SupportedFilesInterface;
use Ufz\ApiBase\Service\Media\ThumbnailInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufz\ApiBase\Service\Media\UrlSignationInterface;
use Symfony\Component\HttpFoundation\File\File;

class PublicUploadServiceTest extends TestCase
{
    private MockObject $mediaUpload;
    private MockObject $supportedFiles;
    private MockObject $thumbnails;
    private MockObject $namer;
    private MockObject $entityManager;
    private MockObject $normalizer;
    private MockObject $signUrl;
    private MockObject $imagePersister;
    private PublicUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaUpload = $this->createMock(MediaItemUploadInterface::class);
        $this->supportedFiles = $this->createMock(SupportedFilesInterface::class);
        $this->thumbnails = $this->createMock(ThumbnailInterface::class);
        $this->namer = $this->createMock(BucketDirectoryNamerService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->signUrl = $this->createMock(UrlSignationInterface::class);
        $this->imagePersister = $this->createMock(UploadImagePersisterInterface::class);

        $this->service = new PublicUploadService(
            $this->mediaUpload,
            $this->supportedFiles,
            $this->thumbnails,
            $this->namer,
            $this->entityManager,
            $this->normalizer,
            $this->signUrl,
            $this->imagePersister
        );
    }

    public function testUploadImage()
    {
        $testImagePath = __DIR__ . '/../../fixtures/bach.png';
        $imageFile = new File($testImagePath);
        $image = new UploadedFile(
            $testImagePath,
            'name.png',
            AbstractUploadService::IMAGE_MIME_PREFIX . '/png',
            null,
            true
        );

        $document = self::createMock(UploadedFile::class);
        $document->method('getClientOriginalName')->willReturn('name2');
        $document->method('getMimeType')->willReturn('pdf');

        $this->supportedFiles->method('isSupported')->willReturn(true);

        $thumbnailLabel = 'thumbnailLabel';
        $thumbnailMediaItem = new MediaItem();
        $thumbnailMediaItem->setFile($imageFile);
        $thumbnails = [[$thumbnailLabel, $thumbnailMediaItem]];
        $this->thumbnails->expects(self::once())->method('generate')->willReturn($thumbnails);

        $imageUrl = 'imageUrl';
        $thumbnailUrl = 'thumbnailUrl';
        $docUrl = 'docUrl';
        $urls = [$imageUrl, $thumbnailUrl, $docUrl];
        $this->mediaUpload->expects(self::exactly(3))->method('upload')
            ->willReturnOnConsecutiveCalls(...$urls);

        // Configure normalizer to return URL from normalized ImageRef/ThumbnailRef objects
        $this->normalizer->method('normalize')->willReturnOnConsecutiveCalls($imageUrl, $thumbnailUrl);

        $result = ($this->service)([$image, $document]);

        $expected = [
            [PublicUploadService::ORIGINAL => $imageUrl, $thumbnailLabel => $thumbnailUrl],
            [PublicUploadService::DOCUMENT => $docUrl]
        ];
        self::assertEquals($expected, $result);
    }

}
