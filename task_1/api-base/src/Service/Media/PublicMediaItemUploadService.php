<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
use Ufz\ApiBase\DTO\MediaItem;
use RuntimeException;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicMediaItemUploadService implements MediaItemUploadInterface
{
    public function __construct(
        private readonly UploadHandler $uploadHandler,
        private readonly UploaderHelper $uploaderHelper,
        private readonly string $publicStorageUrl,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'public.media.storage')]
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function upload(MediaItem $item): string
    {
        try {
            $this->uploadHandler->upload($item, MediaItem::FILE_PROPERTY);
        } catch (CannotWriteFileException $e) {
            $this->logS3Diagnostic($item, $e);
            throw $e;
        }

        $path = $this->uploaderHelper->asset($item, MediaItem::FILE_PROPERTY);

        if (!$path) {
            throw new RuntimeException('Uploaded file ' . $item->getName() . ' was not found in object storage.');
        }
        return $path;
    }

    public function getStorageUrl(): string
    {
        return $this->publicStorageUrl;
    }

    private function logS3Diagnostic(MediaItem $item, CannotWriteFileException $original): void
    {
        $file = $item->getFile();
        $context = [
            'vich_error' => $original->getMessage(),
            'file_name' => $item->getName(),
            'file_exists' => $file !== null && file_exists($file->getRealPath()),
            'file_size' => $file?->getSize(),
            'file_mime' => $file?->getMimeType(),
            'file_real_path' => $file?->getRealPath(),
        ];

        try {
            $testKey = '_upload_diagnostic_' . uniqid() . '.txt';
            $this->filesystem->write($testKey, 'diagnostic');
            $this->filesystem->delete($testKey);
            $this->logger->error('S3 upload failed via VichUploader but direct Flysystem write succeeded.', $context);
        } catch (\Throwable $directError) {
            $context['direct_error'] = $directError->getMessage();
            $context['direct_error_class'] = get_class($directError);
            $prev = $directError->getPrevious();
            while ($prev !== null) {
                $context['direct_error_previous_' . get_class($prev)] = $prev->getMessage();
                $prev = $prev->getPrevious();
            }
            $this->logger->error('S3 upload failed - direct Flysystem write also failed.', $context);
        }
    }
}
