<?php

namespace Ufz\ApiBase\Interfaces;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;

interface UserInterface
{
    public function getId(): mixed;

    public function getUsername(): ?string;

    public function setUsername(string $username): self;

    public function getUserId(): string;

    public function setUserId(string $userId): self;

    public function getEmail(): ?string;

    public function setEmail(?string $email): self;

    public function getFirstName(): ?string;

    public function setFirstName(?string $firstName): self;

    public function getLastName(): ?string;

    public function setLastName(?string $lastName): self;

    public function getTitle(): ?string;

    public function setTitle(?string $title): self;

    public function getAddress(): ?string;

    public function setAddress(?string $address): self;

    public function getZip(): ?string;

    public function setZip(?string $zip): self;

    public function getCity(): ?string;

    public function setCity(?string $city): self;

    public function getLastLogin(): ?DateTimeInterface;

    public function setLastLogin(?DateTimeInterface $lastLogin): self;

    public function getRegisteredOn(): ?DateTimeInterface;

    public function setRegisteredOn(? DateTimeInterface $registeredOn): self;

    public function getMarkedForDelete(): bool;

    public function setMarkedForDelete(bool $markedForDelete): self;

    public function setIsActive(bool $isActive): self;

    public function getUserRoles(): Collection;

    public function addUserRole(UserRoleInterface $userRole): self;

    public function removeUserRole(UserRoleInterface $userRole): self;

    public function clearUserRoles(): self;

    public function hasRole(string $roleName): bool;

    public function getRoleByLabel(string $label): ?UserRoleInterface;

    public function getProfileImageRef(): ?ImageRefInterface;

    public function setProfileImageRef(?ImageRefInterface $profileImageRef): self;

    public function getProfileThumbnailRefs(): ?Collection;
}
