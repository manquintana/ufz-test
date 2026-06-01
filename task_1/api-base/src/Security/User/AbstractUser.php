<?php

namespace Ufz\ApiBase\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractUser implements UserInterface
{

    /** @var string */
    private string $sub;

    /** @var string */
    private string $aud;

    /** @var string */
    private string $iss;

    /** @var string */
    private string $username;

    /** @var string */
    private string $clientID;

    /** @var string[] */
    private array $roles = [];

    /**
     * @return string
     */
    public function getSub(): string
    {
        return $this->sub;
    }

    /**
     * @param string $sub
     *
     * @return AbstractUser
     */
    public function setSub(string $sub): AbstractUser
    {
        $this->sub = $sub;

        return $this;
    }

    /**
     * @return string
     */
    public function getAud(): string
    {
        return $this->aud;
    }

    /**
     * @param string $aud
     *
     * @return AbstractUser
     */
    public function setAud(string $aud): AbstractUser
    {
        $this->aud = $aud;

        return $this;
    }

    /**
     * @return string
     */
    public function getIss(): string
    {
        return $this->iss;
    }

    /**
     * @param string $iss
     *
     * @return AbstractUser
     */
    public function setIss(string $iss): AbstractUser
    {
        $this->iss = $iss;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return AbstractUser
     */
    public function setUsername(string $username): AbstractUser
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getClientID(): string
    {
        return $this->clientID;
    }

    /**
     * @param string $clientID
     *
     * @return AbstractUser
     */
    public function setClientID(string $clientID): AbstractUser
    {
        $this->clientID = $clientID;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param string[] $roles
     *
     * @return AbstractUser
     */
    public function setRoles(array $roles): AbstractUser
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): ?string
    {
        // not needed for apps that do not check user passwords
        return null;
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        // not needed for apps that do not check user passwords
        return null;
    }

    /**
     * @see UserInterface
     * @deprecated Since Symfony 7.3, method is empty and no longer needed.
     */
    #[\Deprecated(message: 'Method is empty and no longer needed since Symfony 7.3', since: 'symfony/security-http 7.3')]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

}
