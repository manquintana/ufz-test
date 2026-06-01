<?php

namespace Ufz\ApiBase\Service\Media\ProfilePictures;

use Ufz\ApiBase\Interfaces\ProfilePictureUserInterface;

interface UserProfilePictureInterface
{
    /**
     * Update the profile picture of a user.
     * @param ProfilePictureUserInterface $user
     * @param int $ref ref of the original image
     */
    public function updateUserProfilePicture(ProfilePictureUserInterface $user, string $ref);
}
