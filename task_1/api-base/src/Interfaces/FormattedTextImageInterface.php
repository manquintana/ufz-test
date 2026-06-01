<?php

namespace Ufz\ApiBase\Interfaces;

interface FormattedTextImageInterface
{
    public function getId(): ?int;

    public function getKey(): string;

    public function setKey(string $key): static;

    public function getContent(): ?FormattedTextInterface;

    public function setContent(?FormattedTextInterface $content): static;

    public function getImageRef(): ?ImageRefInterface;

    public function setImageRef(?ImageRefInterface $imageRef): static;
}
