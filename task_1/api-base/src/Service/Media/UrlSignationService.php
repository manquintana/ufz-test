<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Aws\S3\S3ClientInterface;

class UrlSignationService implements UrlSignationInterface
{
    private string $bucket;
    private S3ClientInterface $s3Client;
    private string $expires;
    private string $restrictedStorageUrl;

    /**
     * UrlSignationService constructor.
     * @param string $bucket
     * @param string $expires
     * @param S3ClientInterface $s3Client
     * @param string $restrictedStorageUrl
     */
    public function __construct(
        string $bucket,
        string $expires,
        S3ClientInterface $s3Client,
        string $restrictedStorageUrl
    ) {
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
        $this->expires = $expires;
        $this->restrictedStorageUrl = $restrictedStorageUrl;
    }

    /**
     * @param string $url
     * @return string
     */
    public function __invoke(string $url): string
    {
        $key = ltrim(
            str_replace($this->restrictedStorageUrl, '', $url),
            '/'
        );

        $accessMedia = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key
        ]);

        $request = $this->s3Client->createPresignedRequest($accessMedia, $this->expires);
        return (string)$request->getUri();
    }
}
