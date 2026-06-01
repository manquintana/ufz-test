<?php

namespace Ufz\ApiBase\Security\Permission;


use Doctrine\Common\Collections\Criteria;

class EntityPermission
{

    protected ?string $entityName = null;
    protected bool $isReadable = false;
    protected array $limitedReadableFields = [];
    protected bool $isCreateable = false;
    protected array $limitedCreatableFields = [];
    protected bool $isUpdateable = false;
    protected array $limitedUpdateableFields = [];
    protected bool $isDeleteable = false;
    protected ?Criteria $readDataFilter = null;
    protected ?Criteria $updateDataFilter = null;
    protected ?Criteria $deleteDataFilter = null;

    public function __construct(string $entityName)
    {
        $this->entityName = $entityName;
    }

    /**
     * @return string|null
     */
    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    /**
     * @param string|null $entityName
     *
     * @return EntityPermission
     */
    public function setEntityName(?string $entityName): EntityPermission
    {
        $this->entityName = $entityName;

        return $this;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->isReadable;
    }

    /**
     * @return bool
     */
    public function isFullyReadable(): bool
    {
        return $this->isReadable && empty($this->limitedReadableFields);
    }

    /**
     * @return bool
     */
    public function isPartlyReadable(): bool
    {
        return $this->isReadable && !empty($this->limitedReadableFields);
    }

    /**
     * @return EntityPermission
     */
    public function allowRead(): EntityPermission
    {
        $this->isReadable = true;

        return $this;
    }

    /**
     * @return EntityPermission
     */
    public function forbidRead(): EntityPermission
    {
        $this->isReadable = false;

        return $this;
    }

    /**
     * @return array
     */
    public function getLimitedReadableFields(): array
    {
        return $this->limitedReadableFields;
    }

    /**
     * @param array $limitedReadableFields
     *
     * @return EntityPermission
     */
    public function setLimitedReadableFields(array $limitedReadableFields): EntityPermission
    {
        $this->limitedReadableFields = $limitedReadableFields;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCreateable(): bool
    {
        return $this->isCreateable;
    }

    /**
     * @return bool
     */
    public function isFullyCreateable(): bool
    {
        return $this->isCreateable && empty($this->limitedCreatableFields);
    }

    /**
     * @return bool
     */
    public function isPartlyCreateable(): bool
    {
        return $this->isCreateable && !empty($this->limitedCreatableFields);
    }

    /**
     * @return EntityPermission
     */
    public function allowCreate(): EntityPermission
    {
        $this->isCreateable = true;

        return $this;
    }

    /**
     * @return EntityPermission
     */
    public function forbidCreate(): EntityPermission
    {
        $this->isCreateable = false;

        return $this;
    }

    /**
     * @return array
     */
    public function getLimitedCreatableFields(): array
    {
        return $this->limitedCreatableFields;
    }

    /**
     * @param array $limitedCreatableFields
     *
     * @return EntityPermission
     */
    public function setLimitedCreatableFields(array $limitedCreatableFields): EntityPermission
    {
        $this->limitedCreatableFields = $limitedCreatableFields;

        return $this;
    }

    /**
     * @return bool
     */
    public function isUpdateable(): bool
    {
        return $this->isUpdateable;
    }

    /**
     * @return bool
     */
    public function isFullyUpdateable(): bool
    {
        return $this->isUpdateable && empty($this->limitedUpdateableFields);
    }

    /**
     * @return bool
     */
    public function isPartlyUpdateable(): bool
    {
        return $this->isUpdateable && !empty($this->limitedUpdateableFields);
    }

    /**
     * @return EntityPermission
     */
    public function allowUpdate(): EntityPermission
    {
        $this->isUpdateable = true;

        return $this;
    }

    /**
     * @return EntityPermission
     */
    public function forbidUpdate(): EntityPermission
    {
        $this->isUpdateable = false;

        return $this;
    }

    /**
     * @return array
     */
    public function getLimitedUpdateableFields(): array
    {
        return $this->limitedUpdateableFields;
    }

    /**
     * @param array $limitedUpdateableFields
     *
     * @return EntityPermission
     */
    public function setLimitedUpdateableFields(array $limitedUpdateableFields): EntityPermission
    {
        $this->limitedUpdateableFields = $limitedUpdateableFields;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleteable(): bool
    {
        return $this->isDeleteable;
    }

    /**
     * @return EntityPermission
     */
    public function allowDelete(): EntityPermission
    {
        $this->isDeleteable = true;

        return $this;
    }

    /**
     * @return EntityPermission
     */
    public function forbidDelete(): EntityPermission
    {
        $this->isDeleteable = false;

        return $this;
    }

    /**
     * @return Criteria|null
     */
    public function getReadDataFilter(): ?Criteria
    {
        return $this->readDataFilter;
    }

    /**
     * @param Criteria|null $readDataFilter
     *
     * @return EntityPermission
     */
    public function setReadDataFilter(?Criteria $readDataFilter): EntityPermission
    {
        $this->readDataFilter = $readDataFilter;

        return $this;
    }

    /**
     * @return Criteria|null
     */
    public function getUpdateDataFilter(): ?Criteria
    {
        return $this->updateDataFilter;
    }

    /**
     * @param Criteria|null $updateDataFilter
     *
     * @return EntityPermission
     */
    public function setUpdateDataFilter(?Criteria $updateDataFilter): EntityPermission
    {
        $this->updateDataFilter = $updateDataFilter;

        return $this;
    }

    /**
     * @return Criteria|null
     */
    public function getDeleteDataFilter(): ?Criteria
    {
        return $this->deleteDataFilter;
    }

    /**
     * @param Criteria|null $deleteDataFilter
     *
     * @return EntityPermission
     */
    public function setDeleteDataFilter(?Criteria $deleteDataFilter): EntityPermission
    {
        $this->deleteDataFilter = $deleteDataFilter;

        return $this;
    }


}