<?php

namespace Ufz\ApiBase\Tests\Service\Media\ProfilePictures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\MediaDeletionInterface;
use Ufz\ApiBase\Service\Media\ProfilePictures\UserProfilePictureService;
use Ufz\ApiBase\Tests\Entity\UserImplementation;
//use Ufz\ApiCore\Entity\ImageRef;
//use Ufz\ApiCore\Entity\ThumbnailRef;

/**
 * TODO
 * The code was commented out because the tests created circular dependencies between base-api and core-api.
 * This will be resolved by switching to a mono repo, and then the tests can be re-enabled.
 */

class UserProfilePictureTest extends TestCase
{
    /**
     * @var MediaDeletionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $deletion;
    /**
     * @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $entityManager;

    /**
     * @var UploadImagePersisterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $imagePersister;

    private UserProfilePictureService $service;

//    protected function setUp(): void
//    {
//        parent::setUp();
//        $this->deletion = self::createMock(MediaDeletionInterface::class);
//        $this->entityManager = self::createMock(EntityManagerInterface::class);
//        $this->imagePersister = self::createMock(UploadImagePersisterInterface::class);
//        $this->service = new UserProfilePictureService($this->deletion, $this->entityManager, $this->imagePersister);
//
//        $ref = 'url';
//
//        $mockImageRef = $this->createMock(ImageRef::class);
//        $mockImageRef->method('getRef')->willReturn($ref);
//
//        $this->imagePersister->method('getImageRefByRef')
//            ->willReturnCallback(function (string $ref) {
//                $mediumThumbnailRef = new ThumbnailRef();
//                $mediumThumbnailRef->setRef('url_medium');
//                $smallThumbnailRef = new ThumbnailRef();
//                $smallThumbnailRef->setRef('url_small');
//                $thumbnailRefs = new ArrayCollection([$mediumThumbnailRef, $smallThumbnailRef]);
//
//                $mockImageRef = $this->createMock(\Ufz\ApiCore\Entity\ImageRefInterface::class);
//                $mockImageRef->method('getRef')->willReturn($ref);
//                $mockImageRef->method('setProfilePictureUser')->willReturn($mockImageRef);
//                $mockImageRef->method('getThumbnailRefs')->willReturn($thumbnailRefs);
//                return [$mockImageRef];
//            });
//
//        $this->imagePersister->expects(self::once())
//            ->method('getImageRefByRef')
//            ->with($ref)
//            ->willReturn([$mockImageRef])
//        ;
//    }

//    public function testUpdateUserProfilePicture()
//    {
//        $user = new UserImplementation();
//        $urls = ['original' => 'url', 'medium' => 'url_medium', 'small' => 'url_small'];
//
//        $this->deletion->expects(self::never())->method('delete');
//        $this->entityManager->expects(self::exactly(2))->method('persist');
//        $this->entityManager->expects(self::once())->method('flush');
//
//        $this->service->updateUserProfilePicture($user, $urls['original']);
//
//        self::assertEquals($urls['original'], $user->getProfileImageRef()->getRef());
//        self::assertEquals($urls['medium'], $user->getProfileImageRef()->getThumbnailRefs()[0]->getRef());
//        self::assertEquals($urls['small'], $user->getProfileImageRef()->getThumbnailRefs()[1]->getRef());
//    }

//    public function testUpdateUserProfilePictureReplaceExisting()
//    {
//        $previousOriginal = 'sub.domain.tld/dir/uploads/previous.ext';
//        $previousMedium = 'sub.domain.tld/dir/uploads/thumbnails/previous_medium.ext';
//        $previousSmall = 'sub.domain.tld/dir/uploads/thumbnails/previous_small.ext';
//
//        $mediumThumbnailRef = new ThumbnailRef();
//        $mediumThumbnailRef->setRef($previousMedium);
//        $smallThumbnailRef = new ThumbnailRef();
//        $smallThumbnailRef->setRef($previousSmall);
//        $thumbnailRefs = new ArrayCollection([$mediumThumbnailRef, $smallThumbnailRef]);
//
//        $imageRef = $this->createMock(ImageRef::class);
//        $imageRef->method('getId')->willReturn(1);
//        $imageRef->method('getRef')->willReturn($previousOriginal);
//        $imageRef->method('getThumbnailRefs')->willReturn($thumbnailRefs);
//
//        $user = (new UserImplementation())->setProfileImageRef($imageRef);
//
//        $urls = ['original' => 'url', 'medium' => 'url_medium', 'small' => 'url_small'];
//        $expectedPath = AbstractUploadService::UPLOADS_FOLDER . DIRECTORY_SEPARATOR . 'previous.ext';
//
//        $this->deletion->expects(self::once())->method('delete')->with($expectedPath);
//        $this->entityManager->expects(self::exactly(2))->method('persist');
//        $this->entityManager->expects(self::once())->method('flush');
//
//        $this->service->updateUserProfilePicture($user, $urls['original']);
//
//        self::assertEquals($urls['original'], $user->getProfileImageRef()->getRef());
//        self::assertEquals($urls['medium'], $user->getProfileImageRef()->getThumbnailRefs()[0]->getRef());
//        self::assertEquals($urls['small'], $user->getProfileImageRef()->getThumbnailRefs()[1]->getRef());
//    }

}
