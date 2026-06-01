<?php

namespace Ufz\ApiBase\Interfaces;

interface FormattedTextAttachmentInterface
{
    public function getId(): ?int;

    public function getKey(): string;

    public function setKey(string $key): static;

    public function getRef(): string;

    public function setRef(string $ref): static;

    public function getFileName(): string;

    public function setFileName(string $fileName): static;

    public function getFileSize(): ?int;

    public function setFileSize(?int $fileSize): static;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): static;
}
