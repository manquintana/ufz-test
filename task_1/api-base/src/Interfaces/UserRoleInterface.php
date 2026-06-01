<?php

namespace Ufz\ApiBase\Interfaces;

use Doctrine\Common\Collections\Collection;

interface UserRoleInterface
{
    public function getId(): ?int;

    public function getLabel(): string;

    public function setLabel(string $label): self;

    public function getUsers(): Collection;

    public function addUser(UserInterface $user): self;

    public function removeUser(UserInterface $user): self;
}
