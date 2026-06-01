<?php


namespace Ufz\ApiBase\Service\Media;


use Aws\S3\S3ClientInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaDeletionService implements MediaDeletionInterface
{
    private const BUCKET = 'Bucket';
    private const KEY = 'Key';

    private S3ClientInterface $s3Client;
    private ThumbnailNamingService $thumbnailNames;
    private string $bucket;

    public function __construct(
        string $bucket,
        S3ClientInterface $s3Client,
        ThumbnailNamingService $thumbnailNames
    )
    {
        $this->bucket = $bucket;
        $this->s3Client = $s3Client;
        $this->thumbnailNames = $thumbnailNames;
    }

    private function s3Delete(string $path)
    {
        $this->s3Client->deleteObject([
            self::BUCKET => $this->bucket,
            self::KEY => $path
        ]);
    }

    public function delete(string $path): void
    {
        $this->s3Delete($path);

        $thumbnails = $this->thumbnailNames->thumbnailsForFile($path);
        array_walk($thumbnails, function ($thumbnail) {
            $path = AbstractUploadService::THUMBNAILS_FOLDER . DIRECTORY_SEPARATOR . $thumbnail;
            $this->s3Delete($path);
        });
    }

    public function deleteMany(array $paths): void
    {
        $nonexisting = array_filter($paths, function (string $path): bool {
            return !$this->s3Client->doesObjectExist($this->bucket, $path);
        });

        if (!empty($nonexisting)) {
            $msg = array_reduce($nonexisting, function (string $acc, string $item): string {
                $acc .= $item . ' ';
                return $acc;
            }, 'Files to delete not found: ');

            throw new NotFoundHttpException($msg);
        }

        array_walk($paths, function (string $path) {
            $this->delete($path);
        });

    }
}
