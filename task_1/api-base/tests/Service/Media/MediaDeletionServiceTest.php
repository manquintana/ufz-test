<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use Ufz\ApiBase\Service\Media\MediaDeletionService;
use Ufz\ApiBase\Service\Media\ThumbnailNamingService;
use Aws\S3\S3ClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaDeletionServiceTest extends TestCase
{
    private const BUCKET = 'BUCKET';
    private MockObject $s3Client;
    private MockObject $thumbnailNamingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s3Client = $this->createMock(S3ClientInterface::class);
        $this->thumbnailNamingService = $this->createMock(ThumbnailNamingService::class);
    }

    public function testDelete()
    {
        $service = new MediaDeletionService(self::BUCKET, $this->s3Client, $this->thumbnailNamingService);

        $this->thumbnailNamingService->method('thumbnailsForFile')->willReturn(['a', 'b']);
        // method s3Client.deleteObject only exists as an annotation, so we can not mock it directly
        $this->s3Client->expects(self::exactly(3))->method('__call')->with('deleteObject', self::anything());

        $service->delete('filename');
    }

    public function testDeleteManyNonexisting()
    {
        $service = new MediaDeletionService(self::BUCKET, $this->s3Client, $this->thumbnailNamingService);

        $this->s3Client->expects(self::exactly(2))
            ->method('doesObjectExist')->willReturnOnConsecutiveCalls(true, false);
        self::expectException(NotFoundHttpException::class);
        self::expectExceptionMessage('Files to delete not found: b ');

        $service->deleteMany(['a', 'b']);
    }

    public function testDeleteMany()
    {
        $service = new MediaDeletionService(self::BUCKET, $this->s3Client, $this->thumbnailNamingService);

        $this->s3Client->expects(self::exactly(2))
            ->method('doesObjectExist')->willReturnOnConsecutiveCalls(true, true);
        $this->thumbnailNamingService->method('thumbnailsForFile')->willReturn(['1', '2']);
        $this->s3Client->expects(self::exactly(6))->method('__call')->with('deleteObject', self::anything());

        $service->deleteMany(['a', 'b']);
    }
}
