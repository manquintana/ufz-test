<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Imagine\Gd\Imagine;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;

class RestrictedUploadService extends AbstractUploadService
{
    private MediaItemUploadInterface $upload;
    
    private EntityManagerInterface $entityManager;
    
    private NormalizerInterface $normalizer;
    
    private UrlSignationInterface $signUrl;
    
    private UploadImagePersisterInterface $imagePersister;
    
    /**
     * RestrictedUploadService constructor.
     **/
    public function __construct(
        MediaItemUploadInterface $restrictedMediaItemUpload,
        SupportedFilesInterface $restrictedSupportedFiles,
        ThumbnailInterface $thumbnailService,
        EntityManagerInterface $entityManager,
        NormalizerInterface $itemNormalizer,
        UrlSignationInterface $signUrl,
        BucketDirectoryNamerService $directoryNamerService,
        UploadImagePersisterInterface $imagePersister
    )
    {
        parent::__construct($restrictedSupportedFiles, $thumbnailService, $directoryNamerService);
        $this->upload = $restrictedMediaItemUpload;
        $this->entityManager = $entityManager;
        $this->normalizer = $itemNormalizer;
        $this->signUrl = $signUrl;
        $this->imagePersister = $imagePersister;
    }

    /**
     * {@inheritDoc}
     */
    protected function uploadMedia(array $imagesWithThumbnails, array $documents): array
    {
        if (count($documents) != 0) {
            throw new DomainException('Restricted upload does not allow documents.');
        }

        $items = array_map(function ($imageWithThumbnails) {
            [$image, $labeledThumbnails] = $imageWithThumbnails;
            $size = (new Imagine())->open($image->getFile())->getSize();
            $this->directoryNamerService->setDirectory(self::UPLOADS_FOLDER);
            $ref = $this->upload->upload($image);
            
            $imageReference = $this->imagePersister->persistImage($ref, $size);

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

        return $items;
    }

    public function getStorageUrl(): string
    {
        return $this->upload->getStorageUrl();
    }
}
