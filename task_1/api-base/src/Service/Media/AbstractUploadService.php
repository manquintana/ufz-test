<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class AbstractUploadService checks for unsupported files, creates thumbnails and splits files by their types.
 * Implementations need to provide own ways to upload files via function AbstractUploadService:::uploadMedia.
 *
 * File size keys 'original', 'medium', 'small' to identify original file size aund thumbnail file size entries in an array.
 * e.g. ['original'=> '42ab42cd0815_image.jpg', 'medium' => '42ab42cd0815_image_medium.jpg', 'small' => '42ab42cd0815_image_small.jpg']
 */
abstract class AbstractUploadService
{
    const ORIGINAL = 'original';
    const MEDIUM = 'medium';
    const SMALL = 'small';
    const IMAGE_MIME_PREFIX = 'image';

    const UPLOADS_FOLDER = 'uploads';
    const THUMBNAILS_FOLDER = 'uploads/thumbnails';
    const ERROR_UNSUPPORTED_FILES = 'Request contains unsupported files: ';

    private SupportedFilesInterface $supportedFilesService;

    private ThumbnailInterface $thumbnailService;

    protected BucketDirectoryNamerService $directoryNamerService;

    /**
     * AbstractUploadService constructor.
     * @param SupportedFilesInterface $supportedFilesService
     * @param ThumbnailInterface $thumbnailService
     * @param BucketDirectoryNamerService $directoryNamerService
     */
    public function __construct(
        SupportedFilesInterface $supportedFilesService,
        ThumbnailInterface $thumbnailService,
        BucketDirectoryNamerService $directoryNamerService
    ) {
        $this->supportedFilesService = $supportedFilesService;
        $this->thumbnailService = $thumbnailService;
        $this->directoryNamerService = $directoryNamerService;
    }

    /**
     * @param array $imagesWithThumbnails
     * @param array $documents
     * @return array
     */
    abstract protected function uploadMedia(array $imagesWithThumbnails, array $documents): array;

    /**
     * Creates an UploadedFile from a base64 string.
     *
     * @param string $base64Data base64 encoded string (with or without data prefix)
     * @param string $originalName original name for the file
     * @return UploadedFile
     */
    public function createUploadedFileFromBase64(string $base64Data, string $originalName): UploadedFile
    {
        $base64Start = strpos($base64Data, 'base64,');
        if ($base64Start !== false) {
            $base64Start += 7;
            $contentType = str_replace([';base64', 'data:'], '', substr($base64Data, 0, $base64Start - 1));
            $base64content = substr($base64Data, $base64Start);
        } else {
            $base64content = $base64Data;
            $contentType = 'image/png'; // Default if not provided
        }

        $decodedImage = base64_decode($base64content);
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpFilePath, $decodedImage);

        return new UploadedFile($tmpFilePath, $originalName, $contentType, null, true);
    }

    /**
     * Returns the base URL of the storage where the files are uploaded to.
     * (Domain + Bucket)
     * @return string
     */
    abstract public function getStorageUrl(): string;

    /**
     * @param array $files
     * @return array
     */
    public function __invoke(array $files): array
    {
        $this->checkForUnsupportedFiles($files);

        $mediaItems = $this->createMediaItems($files);
        [$images, $documents] = $this->mapImagesAndDocuments($mediaItems);
        $imagesWithThumbnails = $images ? $this->getImagesWithThumbnails($images) : [];

        return $this->uploadMedia($imagesWithThumbnails, $documents);
    }

    /**
     * @param array $files
     * @return MediaItem[]
     */
    protected function createMediaItems(array $files): array
    {
        return array_map(function (UploadedFile $file) {
            $replacedSpecialCharsName = $this->replaceSpecialChars($file->getClientOriginalName());
            $item = new MediaItem();
            $item->setFile($file);
            $item->setName(MediaItem::generateUniqueName($replacedSpecialCharsName));
            return $item;
        }, $files);
    }

    /**
     * @param array $files
     */
    protected function checkForUnsupportedFiles(array $files): void
    {
        $unsupportedFiles = array_filter($files, function (UploadedFile $file) {
            return !$this->supportedFilesService->isSupported($file);
        });

        if (!empty($unsupportedFiles)) {

            $msg = array_reduce($unsupportedFiles, function (string $acc, UploadedFile $item) {
                $acc .= $item->getClientOriginalName() . ' ';
                return $acc;
            }, self::ERROR_UNSUPPORTED_FILES);

            throw new BadRequestHttpException($msg);
        }
    }

    /**
     * @param array $mediaItems
     * @return array[]
     */
    protected function mapImagesAndDocuments(array $mediaItems): array
    {
        $images = array();
        $documents = array();

        foreach ($mediaItems as $mediaItem) {
            $f = $mediaItem->getFile();
            if (str_starts_with($f->getMimeType(), self::IMAGE_MIME_PREFIX)) {
                $images[] = $mediaItem;
            } else {
                $documents[] = $mediaItem;
            }
        }
        return array($images, $documents);
    }

    /**
     * @param array $images
     * @return array[]
     */
    private function getImagesWithThumbnails(array $images): array
    {
        return array_map(function (MediaItem $image) {
            return [$image, $this->thumbnailService->generate($image)];
        }, $images);
    }

    /**
     * Returns the name where all non-allowed special characters have been replaced by an underscore.
     * If the filename should contain more than one dot, all but the last one will be replaced with an underscore.
     *
     * @param string $name
     * @return string
     */
    public function replaceSpecialChars(string $name): string
    {
        if (substr_count($name, '.') > 1) {
            $name = preg_replace('/\.(?=.*\.)/', '_', $name);
        }
        return preg_replace('/[^A-Za-z0-9-_.]/', '_', $name);
    }
}
