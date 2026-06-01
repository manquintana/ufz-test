<?php

namespace Ufz\ApiBase\Interfaces;

interface ThumbnailRefInterface
{
    public function getId(): ?int;

    public function getImageRef(): ?ImageRefInterface;

    public function setImageRef(?ImageRefInterface $imageRef): self;

    public function getRef(): ?string;

    public function setRef(?string $ref): self;

    public function getLabel(): ?string;

    public function setLabel(?string $label): self;

    public function getHeight(): ?int;

    public function setHeight(?int $height): self;

    public function getWidth(): ?int;

    public function setWidth(?int $width): self;

    public function getUrl(): ?string;

    public function setUrl(?string $url): void;

    public function isPublic(): bool;

    public function setIsPublic(bool $isPublic): static;

    public function getVirtualProperties(): array;
}
