<?php

namespace Ufz\ApiBase\Tests\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ufz\ApiBase\Interfaces\ProfilePictureUserInterface;
//use Ufz\ApiCore\Entity\ImageRefInterface;
//use Ufz\ApiCore\Entity\TableConfigInterface;
//use Ufz\ApiCore\Entity\UserConsentInterface;
//use Ufz\ApiCore\Entity\UserInterface;
//use Ufz\ApiCore\Entity\UserRoleInterface;

/**
 * TODO
 * The code was commented out because the tests created circular dependencies between base-api and core-api.
 * This will be resolved by switching to a mono repo, and then the tests can be re-enabled.
 */

class UserImplementation //implements ProfilePictureUserInterface, UserInterface
{
//    protected ?ImageRefInterface $profileImageRef = null;
//
//    protected ?Collection $thumbnailRefs = null;
//
//    public function getProfileImageRef(): ?ImageRefInterface
//    {
//        return $this->profileImageRef;
//    }
//    public function setProfileImageRef(?ImageRefInterface $profileImageRef): self
//    {
//        $this->profileImageRef = $profileImageRef;
//        return $this;
//    }
//
//    public function getProfileThumbnailRefs(): ?Collection
//    {
//        return $this->thumbnailRefs;
//    }
//
//    public function setProfileThumbnailRefs(?Collection $thumbnailRefs): self
//    {
//        $this->thumbnailRefs = $thumbnailRefs;
//        return $this;
//    }

//    public function getId(): mixed
//    {
//        return 1;
//    }
//
//    public function getUsername(): string
//    {
//        return "foo";
//    }
//
//    public function setUsername(string $username): UserInterface
//    {
//        return $this;
//    }
//
//    public function getUserId(): string
//    {
//        return "user/1";
//    }
//
//    public function setUserId(string $userId): UserInterface
//    {
//        return $this;
//    }
//
//    public function getEmail(): ?string
//    {
//        return "foo@bar.de";
//    }
//
//    public function setEmail(?string $email): UserInterface
//    {
//        return $this;
//    }
//
//    public function getFirstName(): ?string
//    {
//        return "Foo";
//    }
//
//    public function setFirstName(?string $firstName): UserInterface
//    {
//        return $this;
//    }
//
//    public function getLastName(): ?string
//    {
//        return "Bar";
//    }
//
//    public function setLastName(?string $lastName): UserInterface
//    {
//        return $this;
//    }
//
//    public function getTitle(): ?string
//    {
//        return "Dr.";
//    }
//
//    public function setTitle(?string $title): UserInterface
//    {
//        return $this;
//    }
//
//    public function getAddress(): ?string
//    {
//        return "Foobarstr. 1";
//    }
//
//    public function setAddress(?string $address): UserInterface
//    {
//        return $this;
//    }
//
//    public function getZip(): ?string
//    {
//        return "12345";
//    }
//
//    public function setZip(?string $zip): UserInterface
//    {
//        return $this;
//    }
//
//    public function getCity(): ?string
//    {
//        return "Foobar";
//    }
//
//    public function setCity(?string $city): UserInterface
//    {
//        return $this;
//    }
//
//    public function getLastLogin(): ?DateTimeInterface
//    {
//        return null;
//    }
//
//    public function setLastLogin(?DateTimeInterface $lastLogin): UserInterface
//    {
//        return $this;
//    }
//
//    public function getRegisteredOn(): ?DateTimeInterface
//    {
//        return null;
//    }
//
//    public function setRegisteredOn(?DateTimeInterface $registeredOn): UserInterface
//    {
//        return $this;
//    }
//
//    public function getMarkedForDelete(): bool
//    {
//        return false;
//    }
//
//    public function setMarkedForDelete(bool $markedForDelete): UserInterface
//    {
//        return $this;
//    }
//
//    public function getIsActive(): bool
//    {
//        return true;
//    }
//
//    public function setIsActive(bool $isActive): UserInterface
//    {
//        return $this;
//    }
//
//    public function getUserRoles(): Collection
//    {
//        return new ArrayCollection();
//    }
//
//    public function addUserRole(UserRoleInterface $userRole): UserInterface
//    {
//        return $this;
//    }
//
//    public function removeUserRole(UserRoleInterface $userRole): UserInterface
//    {
//        return $this;
//    }
//
//    public function clearUserRoles(): UserInterface
//    {
//        return $this;
//    }
//
//    public function hasRole(string $roleName): bool
//    {
//        return true;
//    }
//
//    public function getRoleByLabel(string $label): ?UserRoleInterface
//    {
//        return null;
//    }
//
//    public function getUserConsents(): Collection
//    {
//        return new ArrayCollection();
//    }
//
//    public function addUserConsent(UserConsentInterface $userConsent): UserInterface
//    {
//        return $this;
//    }
//
//    public function removeUserConsent(UserConsentInterface $userConsent): UserInterface
//    {
//        return $this;
//    }
//
//    public function getConsentByName(string $name): ?UserConsentInterface
//    {
//        return null;
//    }
//
//    public function getTableConfigs(): Collection
//    {
//        return new ArrayCollection();
//    }
//
//    public function addTableConfig(TableConfigInterface $tableConfig): UserInterface
//    {
//        return $this;
//    }
//
//    public function removeTableConfig(TableConfigInterface $tableConfig): UserInterface
//    {
//        return $this;
//    }
}
