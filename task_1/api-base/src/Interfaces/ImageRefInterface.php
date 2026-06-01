<?php

namespace Ufz\ApiBase\Interfaces;

interface ImageRefInterface
{
    public function getId(): ?int;

    public function getRef(): ?string;

    public function setRef(?string $ref): self;

    public function isPublic(): bool;

    public function setIsPublic(bool $isPublic): static;
}
