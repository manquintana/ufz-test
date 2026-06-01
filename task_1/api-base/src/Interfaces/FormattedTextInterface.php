<?php

namespace Ufz\ApiBase\Interfaces;

use Doctrine\Common\Collections\Collection;

interface FormattedTextInterface
{

    public function getId(): ?int;

    public function getLocale(): string;

    public function setLocale(string $locale): FormattedTextInterface;

    public function getContent(): string;

    public function setContent(string $content): FormattedTextInterface;

    public function getImages(): Collection|array;

    public function addImage(FormattedTextImageInterface $image): FormattedTextInterface;

    public function removeImage(FormattedTextImageInterface $image): FormattedTextInterface;
}