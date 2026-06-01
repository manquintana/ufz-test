<?php

namespace Ufz\ApiBase\Controller;

use Ufz\ApiBase\Interfaces\UploadImagePersisterInterface;
use Ufz\ApiBase\Service\Media\AbstractUploadService;
use Ufz\ApiBase\Service\Media\IsMediaDeletableInterface;
use Ufz\ApiBase\Service\Media\MediaDeletionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeleteMediaItemAction
{
    const FLAG_RESTRICTED = 'restricted';

    private MediaDeletionService $publicMediaDeletionService;
    private MediaDeletionService $restrictedMediaDeletionService;
    private IsMediaDeletableInterface $isMediaDeletable;
    private UploadImagePersisterInterface $imagePersister;

    private string $restrictedStorageUrl;
    private string $publicStorageUrl;

    public function __construct(
        MediaDeletionService $publicMediaDeletionService,
        MediaDeletionService $restrictedMediaDeletionService,
        IsMediaDeletableInterface $isMediaDeletable,
        UploadImagePersisterInterface $imagePersister,
        string $restrictedStorageUrl,
        string $publicStorageUrl
    )
    {
        $this->publicMediaDeletionService = $publicMediaDeletionService;
        $this->restrictedMediaDeletionService = $restrictedMediaDeletionService;
        $this->isMediaDeletable = $isMediaDeletable;
        $this->imagePersister = $imagePersister;
        $this->restrictedStorageUrl = $restrictedStorageUrl;
        $this->publicStorageUrl = $publicStorageUrl;
    }

    public function __invoke(Request $request)
    {
        $files = json_decode($request->getContent());

        if (!is_array($files) || empty($files)) {
            throw new BadRequestHttpException('requires array of files to delete with at least one element');
        }

        $isRestricted = $request->query->has(self::FLAG_RESTRICTED);

        $paths = array_map(function (string $filename): string {
            return AbstractUploadService::UPLOADS_FOLDER . DIRECTORY_SEPARATOR . $filename;
        }, $files);

        $unsupported = array_filter($paths, function (string $path): bool {
            return !($this->isMediaDeletable)($path);
        });

        if (!empty($unsupported)) {
            $msg = array_reduce($unsupported, function (string $acc, string $item): string {
                $acc .= $item . ' ';
                return $acc;
            }, 'Files not allowed to delete: ');

            throw new AccessDeniedHttpException($msg);
        }

        if ($isRestricted) {
            $this->restrictedMediaDeletionService->deleteMany($paths);
        } else {
            $this->publicMediaDeletionService->deleteMany($paths);
        }

        /* delete image refs from database */
        $storageUrl = $isRestricted ? $this->restrictedStorageUrl : $this->publicStorageUrl;
        foreach ($paths as $path) {
            $this->imagePersister->removeImageByRef($storageUrl . DIRECTORY_SEPARATOR . $path);
        }
    }
}
