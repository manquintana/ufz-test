<?php

namespace Ufz\ApiBase\Interfaces;


/**
 *  Table for storing user-specific configurations for various tables in the frontend.
 */
interface TableConfigInterface
{
    public function getId(): ?int;

    public function setId(int $id): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getColumns(): ?array;

    public function setColumns(?array $columns): self;

    public function getPagination(): ?array;

    public function setPagination(?array $pagination): self;

    public function getLastFilterHistory(): ?int;

    public function setLastFilterHistory(?int $lastFilterHistory): self;

    public function getUser(): UserInterface;

    public function setUser(UserInterface $user): self;
}