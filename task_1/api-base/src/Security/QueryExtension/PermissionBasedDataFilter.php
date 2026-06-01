<?php


namespace Ufz\ApiBase\Security\QueryExtension;


use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class PermissionBasedDataFilter implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    /**
     * @var Security
     */
    private Security $security;
    /**
     * @var PermissionProviderInterface
     */
    private PermissionProviderInterface $permissionProvider;


    public function __construct(
        TokenStorageInterface $tokenStorage,
        Security $security,
        PermissionProviderInterface $permissionProvider
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->security = $security;
        $this->permissionProvider = $permissionProvider;
    }





    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhereClause($queryBuilder, $resourceClass);
    }





    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhereClause($queryBuilder, $resourceClass);
    }





    /**
     * @param QueryBuilder $queryBuilder
     * @param string       $resourceClass
     *
     * @return array|void
     */
    private function addWhereClause(QueryBuilder $queryBuilder, string $resourceClass)
    {

        $filterCriteria = $this->permissionProvider->getCriteriaToFilterReadableDatasetsOfEntity($resourceClass,$this->security->getUser());

        if ($filterCriteria === null) {
            return;
        }
        $queryBuilder->addCriteria($filterCriteria);
    }


}
