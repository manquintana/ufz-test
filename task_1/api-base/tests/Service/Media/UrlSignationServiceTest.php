<?php

namespace Ufz\ApiBase\Tests\Service\Media;

use GuzzleHttp\Psr7\Uri;
use Ufz\ApiBase\Service\Media\UrlSignationService;
use Aws\CommandInterface;
use Aws\S3\S3ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class UrlSignationServiceTest extends TestCase
{

    public function testSignation()
    {
        $inputFile = 'storage_url/file';
        $inputWithoutStorageUrl = 'file';
        $bucket = 'bucket';
        $restrictedStorageUrl = 'storage_url';
        $expectedUrl = 'final_url';

        $s3 = $this->createMock(S3ClientInterface::class);
        $command = $this->createMock(CommandInterface::class);
        $s3->expects(self::once())->method('getCommand')
            ->with(self::equalTo('GetObject'), self::equalTo(['Bucket' => $bucket, 'Key' => $inputWithoutStorageUrl]))
            ->willReturn($command);
        $request = $this->createMock(RequestInterface::class);
        $s3->expects(self::once())->method('createPresignedRequest')->willReturn($request);
        $request->expects(self::once())->method('getUri')->willReturn(new Uri($expectedUrl));

        $sign = new UrlSignationService($bucket, 'expires', $s3, $restrictedStorageUrl);
        self::assertEquals($expectedUrl, ($sign)($inputFile));

    }

}
