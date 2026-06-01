<?php


namespace Ufz\ApiBase\Security\Interfaces;


use Ufz\ApiBase\Security\User\HumanUser;
use Doctrine\Common\Collections\Criteria;

interface HumanUserPermissionsInterface
{
    public function getDefaultRoles(string $username): array;

    public function getReadableFieldNamesOfEntity(string $entityFQCN, HumanUser $user): array;

    public function getCreatableFieldNamesOfEntity(string $entityFQCN, HumanUser $user): array;

    public function getUpdateableFieldNamesOfEntity(string $entityFQCN, HumanUser $user): array;

    public function isDatasetUpdateable($entityInstance, HumanUser $user): bool;

    public function isDatasetDeletable($entityInstance, HumanUser $user): bool;

    public function getCriteriaToFilterReadableDatasetsOfEntity(string $entityFQCN, HumanUser $user): ?Criteria;
}