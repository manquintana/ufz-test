<?php

namespace Ufz\ApiBase\Service\Media\ProfilePictures;

use Ufz\ApiBase\Interfaces\ProfilePictureUserInterface;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\MediaDeletionInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserProfilePictureService implements UserProfilePictureInterface
{
    private MediaDeletionInterface $deletionService;
    private EntityManagerInterface $entityManager;
    private UploadImagePersisterInterface $imagePersister;

    public function __construct(
        MediaDeletionInterface $publicMediaDeletionService,
        EntityManagerInterface $em,
        UploadImagePersisterInterface $imagePersister
    )
    {
        $this->deletionService = $publicMediaDeletionService;
        $this->entityManager = $em;
        $this->imagePersister = $imagePersister;
    }

    /**
     * If a profile picture has been uploaded, all urls returned by the s3 will end up in this method and will be processed along with the logged in user object.
     *
     * @param ProfilePictureUserInterface $user
     * @param string $ref ref of the original image
     */
    public function updateUserProfilePicture(ProfilePictureUserInterface $user, string $ref): void
    {
        if ($user->getProfileImageRef() != null) {
            $this->deleteProfileImages($user);
        }
        $imageRefs = $this->imagePersister->getImageRefByRef($ref);

        if (empty($imageRefs)) {
            return;
        }

        $imageRef = $imageRefs[0];

        $user->setProfileImageRef($imageRef);
        $imageRef->setProfilePictureUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($imageRef);
        $this->entityManager->flush();
    }

    /**
     * Extract the filename from the full original image url stored in image_ref table and
     * add folder names in which the image was stored in s3.
     * e.g. 'http://minio:9000/public/uploads/42ab42cd0815_image.jpg' to 'uploads/42ab42cd0815_image.jpg'
     *
     * This string is then used as an argument when calling the delete method from the deletion service
     * to delete the original image file and its thumbnail files.
     *
     * @param ProfilePictureUserInterface $user
     */
    private function deleteProfileImages(ProfilePictureUserInterface $user): void
    {
        if ($user->getProfileImageRef() != null) {
            $url = $user->getProfileImageRef()->getRef();
            $file = pathinfo($url, PATHINFO_BASENAME);
            $path = AbstractUploadService::UPLOADS_FOLDER . DIRECTORY_SEPARATOR . $file;
            $this->deletionService->delete($path);
            $this->imagePersister->removeImageById($user->getProfileImageRef()->getId());
        }
    }
}
