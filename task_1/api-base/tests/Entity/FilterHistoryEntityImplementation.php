<?php

namespace Ufz\ApiBase\Tests\Entity;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Ufz\ApiBase\Interfaces\FilterHistoryEntityInterface;

class FilterHistoryEntityImplementation implements FilterHistoryEntityInterface
{
    public const MOCKED_USER_ID = 'testUserId';

    public const HISTORY_IMPLEMENTATION = 'historyImplementation';

    public const TYPES_MAP = [
        self::HISTORY_IMPLEMENTATION => EntityImplementation::class
    ];

    protected ?int $id = null;

    protected string $label = "";

    protected string $type = "";

    protected array $filter = [];

    protected bool $isFavorite = false;

    public static function create($user, $label, $type, $filter): self
    {
        $filterHistory = new FilterHistoryEntityImplementation();
        $filterHistory->user = $user;
        $filterHistory->label = $label;
        $filterHistory->type = $type;
        $filterHistory->filter = $filter;

        return $filterHistory;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFilter(): array
    {
        return $this->filter;
    }

    public function setFilter(array $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function getIsFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setIsFavorite(bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }

    public function getClassNameFromType(): string
    {
        if (!array_key_exists($this->type, self::TYPES_MAP)) {
            throw new BadRequestException('Entity type ' . $this->type . ' cannot be resolved.');
        }
        return self::TYPES_MAP[$this->type];
    }

    public function getUserId(): string
    {
        return self::MOCKED_USER_ID;
    }
}
