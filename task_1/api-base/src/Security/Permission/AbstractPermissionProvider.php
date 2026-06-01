<?php


namespace Ufz\ApiBase\Security\Permission;


use Ufz\ApiBase\Security\Interfaces\HumanUserPermissionsInterface;
use Ufz\ApiBase\Security\Interfaces\MachineUserPermissionsInterface;
use Ufz\ApiBase\Security\User\AbstractUser;
use Ufz\ApiBase\Security\User\HumanUser;
use Ufz\ApiBase\Security\User\MachineUser;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractPermissionProvider implements \Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface
{

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;
    /**
     * @var MachineUserPermissionsInterface
     */
    protected MachineUserPermissionsInterface $machineUserPermissions;
    /**
     * @var HumanUserPermissionsInterface
     */
    protected HumanUserPermissionsInterface $humanUserPermissions;

    public function __construct(
        EntityManagerInterface $entityManager,
        MachineUserPermissionsInterface $machineUserPermissions,
        HumanUserPermissionsInterface $humanUserPermissions
    ) {
        $this->entityManager = $entityManager;
        $this->machineUserPermissions = $machineUserPermissions;
        $this->humanUserPermissions = $humanUserPermissions;
    }


    /**
     * Define initial permissions to access entities
     * (later, permissions can be denied, if necessary)
     *
     * @inheritDoc
     */
    public function getDefaultRoles(array $jwtPayload): array
    {
        if ($jwtPayload['handleAsHumanUser'] ?? false) {
            if (!isset($jwtPayload['sub']) || !is_string($jwtPayload['sub'])) {
                throw new \InvalidArgumentException('payload does not contains subject');
            }

            return $this->humanUserPermissions->getDefaultRoles($jwtPayload['sub']);
        }

        if ($jwtPayload['handleAsMachineUser'] ?? false) {
            if (!isset($jwtPayload['aud']) || !is_string($jwtPayload['aud'])) {
                throw new \InvalidArgumentException('payload does not contains audience');
            }

            return $this->machineUserPermissions->getDefaultRoles($jwtPayload['aud']);
        }

        return [];
    }


    public function getReadableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->getReadableFieldNamesOfEntity($entityFQCN, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->getReadableFieldNamesOfEntity($entityFQCN, $user);
        }

        return [];
    }

    public function hasReadAccessToWholeEntity(string $entityFQCN,AbstractUser $user): bool
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->hasReadAccessToWholeEntity($entityFQCN, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->hasReadAccessToWholeEntity($entityFQCN, $user);
        }

        return false;
    }

    public function getCreatableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->getCreatableFieldNamesOfEntity($entityFQCN, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->getCreatableFieldNamesOfEntity($entityFQCN, $user);
        }

        return [];
    }

    public function getUpdateableFieldNamesOfEntity(string $entityFQCN, AbstractUser $user): array
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->getUpdateableFieldNamesOfEntity($entityFQCN, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->getUpdateableFieldNamesOfEntity($entityFQCN, $user);
        }

        return [];
    }

    public function isDatasetUpdateable($entityInstance, AbstractUser $user): bool
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->isDatasetUpdateable($entityInstance, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->isDatasetUpdateable($entityInstance, $user);
        }

        return false;
    }

    public function isDatasetDeletable($entityInstance, AbstractUser $user): bool
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->isDatasetDeletable($entityInstance, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->isDatasetDeletable($entityInstance, $user);
        }

        return false;
    }

    public function getCriteriaToFilterReadableDatasetsOfEntity(string $entityFQCN, AbstractUser $user): ?Criteria
    {
        if ($user instanceof HumanUser) {
            return $this->humanUserPermissions->getCriteriaToFilterReadableDatasetsOfEntity($entityFQCN, $user);
        }
        if ($user instanceof MachineUser) {
            return $this->machineUserPermissions->getCriteriaToFilterReadableDatasetsOfEntity($entityFQCN, $user);
        }

        return null;
    }

}
