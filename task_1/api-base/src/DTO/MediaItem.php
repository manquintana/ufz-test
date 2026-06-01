<?php

namespace Ufz\ApiBase\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Ufz\ApiBase\Controller\CreateMediaItemAction;
use Ufz\ApiBase\Controller\DeleteMediaItemAction;
use Symfony\Component\HttpFoundation\File\File;
use function uniqid;

#[ApiResource(
    normalizationContext: ['groups' => 'media_item_read'],
    operations: [
        new Get(),
        new Delete(status: 200, uriTemplate: '/media_items', controller: DeleteMediaItemAction::class, security: "is_granted('ROLE_MediaItem_DELETE') and is_granted('DELETE_MEDIA_ITEM')"),
        new Post(status: 201, controller: CreateMediaItemAction::class, deserialize: false, security: "is_granted('ROLE_MediaItem_CREATE')", validate: false),
        new GetCollection(),
    ]
)]
#[Vich\Uploadable]
class MediaItem
{
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Returns a unique name without any file extension.
     */
    public static function generateUniqueName(string $name): string
    {
        return pathinfo(uniqid() . '_' . $name, PATHINFO_FILENAME);
    }

    /**
     * Name of the property that contains the uploadable file.
     */
    final const FILE_PROPERTY = 'file';

    /**
     * Name of the property that contains a file for a restricted upload.
     * If this is set, it instructs VichUploader to upload to the restricted bucket.
     */
    final const RESTRICTED_FILE_PROPERTY = 'restrictedFile';

    #[Vich\UploadableField(mapping: 'media_item', fileNameProperty: 'name')]
    private ?File $file = null;

    #[Vich\UploadableField(mapping: 'restricted_media_item', fileNameProperty: 'name')]
    private ?File $restrictedFile = null;

    /**
     * Name of the file. This is set by VichUploaderBundle when the entity is uploaded.
     *
     */
    private ?string $name = null;

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getRestrictedFile(): ?File
    {
        return $this->restrictedFile;
    }

    public function setRestrictedFile(?File $restrictedFile): void
    {
        $this->restrictedFile = $restrictedFile;
    }
}
