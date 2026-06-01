<?php

namespace Ufz\ApiBase\Interfaces;

use DateTimeInterface;

interface UserConsentInterface
{
    public function getId(): ?int;

    public function setId(?int $id): UserConsentInterface;

    public function getIsConsented(): bool;

    public function setIsConsented(bool $isConsented): UserConsentInterface;

    public function getFirstConsentedAt(): ?DateTimeInterface;

    public function setFirstConsentedAt(?DateTimeInterface $firstConsentedAt): UserConsentInterface;

    public function getUser(): ?UserInterface;

    public function setUser(?UserInterface $user): UserConsentInterface;

    public function getUserConsentType(): ?UserConsentTypeInterface;

    public function setUserConsentType(?UserConsentTypeInterface $userConsentType): UserConsentInterface;
}
