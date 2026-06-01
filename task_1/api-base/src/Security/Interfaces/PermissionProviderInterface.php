<?php


namespace Ufz\ApiBase\Security\Interfaces;


use Ufz\ApiBase\Security\User\AbstractUser;
use Doctrine\Common\Collections\Criteria;

interface PermissionProviderInterface
{

    /**
     * Provide Roles with prefix 'ROLE_' which are used to define permissions to access entities
     *
     * @param array $jwtPayload
     *
     *   $jwtPayload['handleAsHumanUser'] == true:   use $jwtPayload['sub'] to get username@domain of human user
     *   $jwtPayload['handleAsMachineUser'] == true: use $jwtPayload['aud'] to get id of machine client
     *
     * @return array
     */
    public function getDefaultRoles(array $jwtPayload): array;

    public function getReadableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array;

    public function hasReadAccessToWholeEntity(string $entityFQCN,AbstractUser $user): bool;

    public function getCreatableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array;

    public function getUpdateableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array;

    public function isDatasetUpdateable($entityInstance, AbstractUser $user): bool;

    public function isDatasetDeletable($entityInstance, AbstractUser $user): bool;

    public function getCriteriaToFilterReadableDatasetsOfEntity(string $entityFQCN, AbstractUser $user): ?Criteria;
}
