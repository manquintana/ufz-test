<?php

namespace Ufz\ApiBase\Interfaces;

use Doctrine\Common\Collections\Collection;

/**
 * User entity in main project that needs to have profile image handling needs to implement this interface.
 */
interface ProfilePictureUserInterface
{
    /**
     * @return ImageRefInterface|null
     */
    public function getProfileImageRef(): ?ImageRefInterface;

    /**
     * @param ImageRefInterface|null $profileImageUrl
     * @return $this
     */
    public function setProfileImageRef(?ImageRefInterface $profileImageRef): self;

    /**
    * @return Collection|null
    */
    public function getProfileThumbnailRefs(): ?Collection;
}
