<?php

namespace Ufz\ApiBase\Security\Permission;

use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Ufz\ApiBase\Security\User\AbstractUser;
use Ufz\ApiBase\Security\Utils\EntityUtils;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class AbstractOidcPermissionProvider
 * @package Ufz\ApiBase\Security\Permission
 *
 * @TODO when separated into security package:
 * I would create abstract class AbstractPermissionProvider with all abstract methods
 * and concrete child classes OidcPermissionProvider and HumanMachinePermissionProvider
 * which can be used in openId and human/machine-user based APIs respectively.
 */
abstract class AbstractOidcPermissionProvider implements PermissionProviderInterface
{
    private array $entityPermissionsByRoleId = [];

    protected const OPERATIONS = ['READ', 'CREATE', 'UPDATE', 'DELETE'];

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    abstract public function getDefaultRoles(array $jwtPayload): array;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getReadableFieldNamesOfEntity(string $entityFQCN, ?AbstractUser $user): array
    {
        if ($user === null) {
            return []; // No user = no readable fields
        }
        return $this->getAccessibleFieldNamesByOperation($entityFQCN, $user, 'READ');
    }

    public function hasReadAccessToWholeEntity(string $entityFQCN, ?AbstractUser $user): bool
    {
        if ($user === null) {
            return false; // No user = no access
        }
        $entityName = EntityUtils::getEntityNameFromFQCN($entityFQCN);
        return $this->isEntityAccessibleByUserAndOperation($user, 'READ', $entityName);
    }

    public function getCreatableFieldNamesOfEntity(string $entityFQCN, ?AbstractUser $user): array
    {
        if ($user === null) {
            return []; // No user = no creatable fields
        }
        return $this->getAccessibleFieldNamesByOperation($entityFQCN, $user, 'CREATE');
    }

    public function getUpdateableFieldNamesOfEntity(string $entityFQCN, ?AbstractUser $user): array
    {
        if ($user === null) {
            return []; // No user = no updateable fields
        }
        return $this->getAccessibleFieldNamesByOperation($entityFQCN, $user, 'UPDATE');
    }

    public function isDatasetUpdateable($entityInstance, ?AbstractUser $user): bool
    {
        if ($user === null) {
            return false; // No user = no update access
        }
        $entityName = EntityUtils::getEntityNameFromFQCN(get_class($entityInstance));
        return $this->isEntityAccessibleByUserAndOperation($user, 'UPDATE', $entityName);
    }

    public function isDatasetDeletable($entityInstance, ?AbstractUser $user): bool
    {
        if ($user === null) {
            return false; // No user = no delete access
        }
        $entityName = EntityUtils::getEntityNameFromFQCN(get_class($entityInstance));
        return $this->isEntityAccessibleByUserAndOperation($user, 'DELETE', $entityName);
    }

    abstract public function getCriteriaToFilterReadableDatasetsOfEntity(string $entityFQCN, AbstractUser $user): ?Criteria;


    /*
     *
     *
     * ########## Non Interface Methods ##########
     *
     *
     */


    /**
     * @param string $entityFQCN
     * @param AbstractUser $user
     * @param string $OPERATION
     *
     * @return array
     */
    protected function getAccessibleFieldNamesByOperation(string $entityFQCN, AbstractUser $user, string $OPERATION): array
    {
        $entityName = EntityUtils::getEntityNameFromFQCN($entityFQCN);
        $isAccessible = $this->isEntityAccessibleByUserAndOperation($user, $OPERATION, $entityName);

        if (!$isAccessible) {
            return [];
        }

        // If entity is readable by user -> all fields are readable
        return EntityUtils::getPropertiesOfEntity($entityFQCN, $this->entityManager);
    }

    /**
     * @brief  Returns if a user can access an entity by a given operation
     * @param AbstractUser $user
     * @param string $OPERATION
     * @param string $entityName
     * @return bool
     */
    protected function isEntityAccessibleByUserAndOperation(AbstractUser $user, string $OPERATION, string $entityName): bool
    {
        return in_array("ROLE_" . $entityName . "_" . $OPERATION, $user->getRoles());
    }


    /*
     *
     *
     *
     * ########## Permissions Handling #########
     *
     *
     *
     */

    protected function addEntityPermission(string $roleId, EntityPermission $entityPermission)
    {
        $this->entityPermissionsByRoleId[$roleId][$entityPermission->getEntityName()] = $entityPermission;
    }

    protected function getEntityPermissionsByRole(string $roleId): array
    {
        return $this->entityPermissionsByRoleId[$roleId] ?? [];
    }

    protected function getEntityPermissionsByRoleAndEntityName(string $roleId, string $entityName): ?EntityPermission
    {
        return $this->entityPermissionsByRoleId[$roleId][$entityName] ?? null;
    }
}
