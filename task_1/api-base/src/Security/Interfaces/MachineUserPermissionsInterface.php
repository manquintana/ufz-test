<?php

namespace Ufz\ApiBase\Security\Interfaces;

use Ufz\ApiBase\Security\User\MachineUser;
use Doctrine\Common\Collections\Criteria;

interface MachineUserPermissionsInterface
{
    public function getDefaultRoles(string $username): array;

    public function getReadableFieldNamesOfEntity(string $entityFQCN, MachineUser $user): array;

    public function getCreatableFieldNamesOfEntity(string $entityFQCN, MachineUser $user): array;

    public function getUpdateableFieldNamesOfEntity(string $entityFQCN, MachineUser $user): array;

    public function isDatasetUpdateable($entityInstance, MachineUser $user): bool;

    public function isDatasetDeletable($entityInstance, MachineUser $user): bool;

    public function getCriteriaToFilterReadableDatasetsOfEntity(string $entityFQCN, MachineUser $user): ?Criteria;
}