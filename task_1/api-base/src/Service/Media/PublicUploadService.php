<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;

class PublicUploadService extends AbstractUploadService
{
    const DOCUMENT = 'document';

    private MediaItemUploadInterface $upload;
    private EntityManagerInterface $entityManager;
    private NormalizerInterface $normalizer;
    private UrlSignationInterface $signUrl;
    private UploadImagePersisterInterface $imagePersister;

    /**
     * PublicUploadService constructor.
     * @param MediaItemUploadInterface $publicMediaItemUpload
     * @param SupportedFilesInterface $publicSupportedFiles
     * @param ThumbnailInterface $thumbnailService
     * @param BucketDirectoryNamerService $directoryNamerService
     */
    public function __construct(
        MediaItemUploadInterface $publicMediaItemUpload,
        SupportedFilesInterface $publicSupportedFiles,
        ThumbnailInterface $thumbnailService,
        BucketDirectoryNamerService $directoryNamerService,
        EntityManagerInterface $entityManager,
        NormalizerInterface $itemNormalizer,
        UrlSignationInterface $signUrl,
        UploadImagePersisterInterface $imagePersister
    ) {
        parent::__construct($publicSupportedFiles, $thumbnailService, $directoryNamerService);
        $this->upload = $publicMediaItemUpload;
        $this->entityManager = $entityManager;
        $this->normalizer = $itemNormalizer;
        $this->signUrl = $signUrl;
        $this->imagePersister = $imagePersister;
    }

    /**
     * @param array $imagesWithThumbnails
     * @param array $documents
     * @return array
     */
    protected function uploadMedia(array $imagesWithThumbnails, array $documents): array
    {
        $imageUrls = array_map(function ($imageWithThumbnails) {
            [$image, $labeledThumbnails] = $imageWithThumbnails;
            $size = (new Imagine())->open($image->getFile())->getSize();
            $this->directoryNamerService->setDirectory(self::UPLOADS_FOLDER);
            $ref = $this->upload->upload($image);

            $imageReference = $this->imagePersister->persistImage($ref, $size, true);

            $response = [self::ORIGINAL => $this->normalizer->normalize($imageReference)];

            $response = array_reduce($labeledThumbnails, function ($acc, $labeledThumbnail) use ($imageReference) {
                [$label, $thumbnail] = $labeledThumbnail;
                $size = (new Imagine())->open($thumbnail->getFile())->getSize();
                $this->directoryNamerService->setDirectory(self::THUMBNAILS_FOLDER);
                $ref = $this->upload->upload($thumbnail);

                $thumbnailRef = $this->imagePersister->persistThumbnail($ref, $label, ($this->signUrl)($ref), $size, $imageReference);

                $acc[$label] = $this->normalizer->normalize($thumbnailRef);
                return $acc;
            }, $response);

            $this->entityManager->flush();
            return $response;

        }, $imagesWithThumbnails);

        $documentUrls = array_map(function ($document) {
            $this->directoryNamerService->setDirectory(self::UPLOADS_FOLDER);
            $ref = $this->upload->upload($document);
            return [self::DOCUMENT => $this->upload->getStorageUrl() . $ref];
        }, $documents);

        return array_merge($imageUrls, $documentUrls);
    }

    public function getStorageUrl(): string
    {
        return $this->upload->getStorageUrl();
    }
}
