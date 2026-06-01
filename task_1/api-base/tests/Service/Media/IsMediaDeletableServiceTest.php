<?php


namespace Ufz\ApiBase\Tests\Service\Media;


use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\IsMediaDeletableService;
use PHPUnit\Framework\TestCase;

class IsMediaDeletableServiceTest extends TestCase
{

    public function testIsDeletable(): void
    {
        $service = new IsMediaDeletableService();

        $deletable = [
            AbstractUploadService::UPLOADS_FOLDER . '/' . 'x.jpg',
            AbstractUploadService::UPLOADS_FOLDER . '/' . 'x.pdf',
            AbstractUploadService::UPLOADS_FOLDER . '/' . 'x',
        ];

        $undeletable = [
            'x/',
            'x.jpg',
            'some_folder/x.jpg',
            AbstractUploadService::UPLOADS_FOLDER . 'x.jpg',
            AbstractUploadService::UPLOADS_FOLDER . 'x/',
            AbstractUploadService::UPLOADS_FOLDER . 'x/x.jpg',
            AbstractUploadService::THUMBNAILS_FOLDER . '/' . 'x.jpg',
            AbstractUploadService::THUMBNAILS_FOLDER . '/' . 'x',
        ];

        array_walk($deletable, fn($path) => self::assertTrue(($service)($path)));
        array_walk($undeletable, fn($path) => self::assertFalse(($service)($path)));
    }

}
