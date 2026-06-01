<?php

namespace Ufz\ApiBase\Interfaces;

/**
 * FilterHistory persistence is on the side of main project. FilterHistory entity in main project
 * must implement this interface in order to be included in filtering logic.
 *
 * @package Ufz\ApiBase\Interfaces
 */
interface FilterHistoryEntityInterface
{
    public function getClassNameFromType(): string;

    public function getUserId(): string;

    public function getId(): ?int;

    public function getLabel(): string;

    public function setLabel(string $label): self;

    public function getType(): string;

    public function setType(string $type): self;

    public function getFilter(): array;

    public function setFilter(array $filter): self;

    public function getIsFavorite(): bool;

    public function setIsFavorite(bool $isFavorite): self;
}
