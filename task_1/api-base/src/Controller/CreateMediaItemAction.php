<?php declare(strict_types=1);

namespace Ufz\ApiBase\Controller;

use Ufz\ApiBase\Interfaces\LoggedUserAwareInterface;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\ProfilePictures\UserProfilePictureInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateMediaItemAction
{
    const FILES_PARAMETER = 'files';
    const FLAG_RESTRICTED = 'restricted';
    const FLAG_PROFILE_PICTURE = 'profile_picture';

    private AbstractUploadService $publicUpload;
    private AbstractUploadService $restrictedUpload;
    private UserProfilePictureInterface $profilePictureService;
    private LoggedUserAwareInterface $userService;

    /**
     * @param AbstractUploadService $publicUploadService
     * @param AbstractUploadService $restrictedUploadService
     * @param UserProfilePictureInterface $profilePictureService
     * @param LoggedUserAwareInterface $userService
     */
    public function __construct(
        AbstractUploadService $publicUploadService,
        AbstractUploadService $restrictedUploadService,
        UserProfilePictureInterface $profilePictureService,
        LoggedUserAwareInterface $userService
    )
    {
        $this->publicUpload = $publicUploadService;
        $this->restrictedUpload = $restrictedUploadService;
        $this->profilePictureService = $profilePictureService;
        $this->userService = $userService;
    }

    /**
     * @param Request $request
     * @return array|Response
     */
    public function __invoke(Request $request)
    {
        $files = $request->files->get(self::FILES_PARAMETER);

        if (!$files || !is_array($files) || count($files) == 0) {
            throw new BadRequestHttpException(
                'At least one file in parameter "files" is required, parameter "files" has to be an array'
            );
        }

        $isRestricted = $request->query->has(self::FLAG_RESTRICTED);
        $uploadProfilePicture = $request->query->has(self::FLAG_PROFILE_PICTURE);

        if ($isRestricted && $uploadProfilePicture) {
            throw new BadRequestHttpException('Query has flags restricted and profile_picture. 
            These flags are mutually exclusive and can\'t be used together.');
        }
        if ($uploadProfilePicture) {
            if (count($files) != 1) {
                throw new BadRequestHttpException('Only one image can be set as a profile picture.');
            }
        }
        if ($isRestricted) {
            $urls = ($this->restrictedUpload)($files);
            // AP 3.x no longer auto-wraps raw arrays in Hydra collection format.
            $hydra = ['hydra:member' => $urls, 'hydra:totalItems' => count($urls)];
            return new Response(json_encode($hydra), Response::HTTP_CREATED);
        }

        $urls = ($this->publicUpload)($files);

        if ($uploadProfilePicture && isset($urls[0][AbstractUploadService::ORIGINAL])) {
            $originalImageRef = $urls[0][AbstractUploadService::ORIGINAL];
            // PublicUploadService now returns normalized ImageRef objects
            $imageRef = $originalImageRef['ref'] ?? $originalImageRef; // Fallback for backward compatibility
            $imageRef = str_replace($this->publicUpload->getStorageUrl(), '', $imageRef);
            $this->profilePictureService->updateUserProfilePicture($this->userService->getLoggedUser(), $imageRef);
        }
        return new Response(json_encode($urls), Response::HTTP_CREATED);
    }
}
