<?php

namespace Ufz\ApiBase\Security\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Ufz\ApiBase\Interfaces\ExternalFilterInterface;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Ufz\ApiBase\Security\User\HumanUser;
use Ufz\ApiBase\Security\User\MachineUser;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use GraphQL\Error\Error;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\SecurityBundle\Security;
use Ufz\ApiBase\Security\Utils\EntityUtils;

/*
 * see https://api-platform.com/docs/core/filters/#creating-custom-doctrine-orm-filters
 *
 * Put the following annotation to an entity to apply generic filters for it:
 *
 *     @ApiFilter(GenericFieldBasedFilter::class, strategy="not-used-but-must-be-filled")
 * 
 * To create also a fuzzySearch parameter, use the following annotation and define the fields, that
 * should be searched (use only properties, that are not relations!):
 * 
 *     @ApiFilter(GenericFieldBasedFilter::class, properties={"fuzzySearch": {"username", "firstName", "lastName", "ldapId"}})
 * 
 */

class GenericFieldBasedFilter extends AbstractFilter
{
    /**
     * Track if a custom order has already been applied for the current request.
     */
    private bool $hasAppliedOrder = false;

    /**
     * @var Security
     */
    private Security $security;

    private PermissionProviderInterface $permissionProvider;

    /**
     * @var ExternalFilterInterface[]
     */
    private $externalFilters = [];

    /**
     * Add external filters for additional filtering (ie. filter by species in Taxonomy API).
     * This method is normally to inject external filters defined in main project.
     *
     * @param ExternalFilterInterface $externalFilter
     * @return $this
     */
    public function addExternalFilter(ExternalFilterInterface $externalFilter): self
    {
        $this->externalFilters[] = $externalFilter;
        return $this;
    }

    /**
     * (this annotation causes that Symfony will call this method automatically and pass the required objects .. aka
     * "autoconfigure")
     *
     * @param Security $security
     * @param PermissionProviderInterface $permissionProvider
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function configurePermissionHandling(
        Security $security,
        PermissionProviderInterface $permissionProvider
    ) {
        $this->security = $security;
        $this->permissionProvider = $permissionProvider;
    }


    protected function filterProperty(
        string $filterName,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {

        /*
         * Handle fuzzySearch filter before any other logic
         */
        if ($filterName == 'fuzzySearch' && isset($this->properties['fuzzySearch'])) {
            $this->handleFuzzySearch($value, $queryBuilder, $resourceClass);

            return;
        }

        /*
         * Ensure that current filter name is supported by current entity class
         * i.e. username_is, username_is_list or username_contains
         */
        if (!$this->filterExists($filterName, $resourceClass)) {
            return;
        }

        /*
         * Ensure that current filter name is accessible / usable by current user
         */
        if (!$this->isFilterNameUsableByCurrentUser($filterName, $resourceClass)) {
            throw new AccessDeniedHttpException('Permission denied to use filter '.$filterName.'.');
        }

        /*
         * Determine field- and filter-related settings of current filter, that's provided by it's name
         */
        $fieldName = $this->getFieldNameByFilterName($filterName, $resourceClass); // i.e. username
        $fieldType = $this->getFieldTypeByFilterName($filterName, $resourceClass); // i.e. decimal
        $filterId = $this->getFilterIdByFilterName($filterName, $resourceClass);   // is, is_not, contains, ...
        $fieldResourceClass = $this->getFieldResourceClassByFilterName($filterName, $resourceClass);


        /*
         * Ensure that filter-related fields are supported
         */
        // fields of related entities must be checked differently  
        if ($this->isPropertyNested($fieldName, $resourceClass)) {
            // i.e. $fieldName = rooms.no; $property = rooms_no_is; 
            [$associationName, $referencedField] = $this->getPartsOfNestedFieldName($fieldName); // i.e. [rooms, no]
            if (!$this->isPropertyMapped($referencedField, $fieldResourceClass)) {
                // i.e. $fieldResourceClass = Ufz\ApiBase\Entity\Room
                return;
            }
        } else {
            /*
             * Check fields of current entity
             */
            if (!$this->isPropertyMapped($fieldName, $resourceClass)) {
                return;
            }
        }


        /*
         * If a filter is provided with multiple values -> we get an array
         * => use also an array if only one value is provided
         */
        if (!is_array($value)) {
            $filterValues[] = $value;
        } else {
            $filterValues = $value;
        }


        /*
         * Define doctrine conditions for selecting data based on the provided filter  
         */
        $params = [];
        $conditions = [];
        $this->presetQueryBuilderParams($queryBuilder, $params);
        $this->extendQueryBuilderParamsAndConditions(
            $queryBuilder,
            $queryNameGenerator,
            $resourceClass,
            $filterValues,
            $filterName,
            $fieldType,
            $fieldName,
            $filterId,
            $fieldResourceClass,
            $params,
            $conditions
        );

        /*
         * Add conditions to query builder
         * => logical combine conditions - depending on using including- or excluding filter
         */
        if (!empty($conditions)) {
            $queryBuilder
                ->andWhere(join(' ' . (($filterId == 'is_not') ? 'AND' : 'OR') . ' ', $conditions))
                ->setParameters($params);
        }
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->hasAppliedOrder = false;
        if (!isset($context['filters'])) {
            parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
            return;
        }

        $filters = $context['filters'];
        $orderFilters = [];
        foreach ($filters as $filterName => $value) {
            if ($this->isOrderFilterName($filterName)) {
                $orderFilters[$filterName] = $value;
                unset($filters[$filterName]);
            }
        }

        $context['filters'] = $filters;
        parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

        if (empty($orderFilters)) {
            return;
        }

        ksort($orderFilters, SORT_STRING);
        foreach ($orderFilters as $filterName => $value) {
            if ($value === null) {
                continue;
            }
            $this->filterProperty(
                $filterName,
                $value,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                $operation
            );
        }
    }

    private function isOrderFilterName(string $filterName): bool
    {
        return str_ends_with($filterName, '_order');
    }


    public function getDescription(string $resourceClass): array
    {
        $description = [];
        // Use getAllFilters() instead of getFiltersThatAreUsableByCurrentUser() so that
        // the GraphQL schema and OpenAPI docs expose all possible filter arguments.
        // Permission checks are enforced at query time in filterProperty().
        $filters = $this->getAllFilters($resourceClass);
        foreach ($filters as $filter) {

            $type = 'string';
            if ($filter['fieldType'] == 'boolean' && $filter['filterId'] != 'order') {
                $type = 'bool';
            }

            if ($filter['isFilterSupportingSingleValue']) {
                $description[$filter['filterName']] = [
                    'property'      => $filter['fieldName'],
                    'type'          => $type,
                    'required'      => false,
                    'is_collection' => false,
                ];
            }

            if ($filter['isFilterSupportingMultipleValues']) {
                $description[$filter['filterName'].'[]'] = [
                    'property'      => $filter['fieldName'],
                    'type'          => $type,
                    'required'      => false,
                    'is_collection' => true,
                ];
            }
        }

        return $description;
    }


    public function getFiltersThatAreUsableByCurrentUser(string $resourceClass, bool $getAlsoNestedFilters = true)
    {
        $allFilters = $this->getAllFilters($resourceClass, $getAlsoNestedFilters);

        $filtersThatAreUsableByCurrentUser = [];
        foreach ($allFilters as $filter) {
            if (!$filter['isFilterUsableByCurrentUser']) {
                continue;
            }
            $filtersThatAreUsableByCurrentUser[] = $filter;
        }

        return $filtersThatAreUsableByCurrentUser;
    }


    public function getAllFilters(string $resourceClass, bool $getAlsoNestedFilters = true, bool $isNestedCall = false)
    {
        $filters = [];

        $resourceClassMetadata = $this->getClassMetadata($resourceClass);
        $fieldNames = $resourceClassMetadata->getFieldNames();
        $referencingFieldNames = $resourceClassMetadata->getAssociationNames();

        /*
         * Determine filters based on fields of current entity class 
         */
        foreach ($fieldNames as $fieldName) {

            $fieldType = $this->getDoctrineFieldType($fieldName, $resourceClass);

            foreach ($this->getPossibleFilterIdsForFieldType($fieldType) as $filterId) {
                $filters[] = [
                    'fieldResourceClass'               => $resourceClass,
                    'filterName'                       => $fieldName.'_'.$filterId,
                    'fieldName'                        => $fieldName,
                    'fieldType'                        => $fieldType,
                    'filterId'                         => $filterId,
                    'strategy'                         => '',
                    'isFilterSupportingSingleValue'    => $this->isFilterSupportingSingleValue($filterId, $fieldType),
                    'isFilterSupportingMultipleValues' => $this->isFilterSupportingMultipleValues(
                        $filterId,
                        $fieldType
                    ),
                    'isFilterUsableByCurrentUser'      => $this->isFieldNameReadableByCurrentUser(
                        $fieldName,
                        $resourceClass
                    ),
                ];
            }
        }

        /*
         * Extend supported filters by fuzzySearch
         */
        if (isset($this->properties['fuzzySearch']) && !$isNestedCall) {
            $filters[] = [
                'fieldResourceClass'               => $resourceClass,
                'filterName'                       => 'fuzzySearch',
                'fieldName'                        => 'fuzzySearch',
                'fieldType'                        => 'string',
                'filterId'                         => 'fuzzySearch',
                'strategy'                         => '',
                'isFilterSupportingSingleValue'    => true,
                'isFilterSupportingMultipleValues' => false,
                'isFilterUsableByCurrentUser'      => $this->isFieldNameReadableByCurrentUser(
                    'fuzzySearch',
                    $resourceClass
                ),
            ];
        }


        /*
         * Extend supported filters by filters of referenced entities 
         */
        if ($getAlsoNestedFilters) {
            foreach ($referencingFieldNames as $fieldName) {
                $fieldType = $this->getDoctrineFieldType($fieldName, $resourceClass);
                $targetClass = $resourceClassMetadata->getAssociationTargetClass($fieldName);

                $nestedFilters = $this->getAllFilters($targetClass, false, true);
                foreach ($nestedFilters as $nestedFilter) {
                    $nestedFilter['filterName'] = $fieldName.'_'.$nestedFilter['filterName']; // add current property name as prefix
                    $nestedFilter['fieldName'] = $fieldName.'.'.$nestedFilter['fieldName']; // add current property name as prefix
                    $filters[] = $nestedFilter;
                }
            }
        }

        return $filters;
    }

    public function filterExists($filterName, $resourceClass)
    {
        try {
            $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);
        } catch (\Exception $e) {
            return false;
        }

        return isset($filterData);
    }

    public function getFilterDataByFilterName($filterName, $resourceClass)
    {
        $filters = $this->getAllFilters($resourceClass);
        foreach ($filters as $filter) {
            if ($filterName == $filter['filterName']) {
                return $filter;
            }
        }
        foreach ($this->externalFilters as $externalFilter) {
            $externalFilters = $externalFilter->getFilters($filterName, $resourceClass);
            foreach ($externalFilters as $externalFilter) {
                if ($filterName == $externalFilter['filterName']) {
                    return $externalFilter;
                }
            }
        }

        throw new \InvalidArgumentException('filter name not found');
    }


    public function isFilterNameUsableByCurrentUser($filterName, $resourceClass)
    {
        try {
            $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);
        } catch (\Exception $e) {
            return false;
        }

        if (!isset($filterData)) {
            return false;
        }

        return $filterData['isFilterUsableByCurrentUser'];
    }


    public function getFieldNameByFilterName($filterName, $resourceClass)
    {
        $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);

        return $filterData['fieldName'];
    }


    public function getFieldTypeByFilterName($filterName, $resourceClass)
    {
        $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);

        return $filterData['fieldType'];
    }


    public function getFilterIdByFilterName($filterName, $resourceClass)
    {
        $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);

        return $filterData['filterId'];
    }


    public function getFieldResourceClassByFilterName($filterName, $resourceClass)
    {
        $filterData = $this->getFilterDataByFilterName($filterName, $resourceClass);

        return $filterData['fieldResourceClass'];
    }


    public function getPossibleFilterIdsForFieldType($fieldType)
    {
        switch ($fieldType) {
            case 'string':
            case 'json':
            case 'text':
                return ['is', 'is_not', 'contains', 'starts_with', 'ends_with', 'lt', 'lte', 'gt', 'gte', 'order'];
                break;
            case 'integer':
            case 'decimal':
            case 'float':
            case 'datetime':
            case 'date':
                return ['is', 'is_not', 'lt', 'lte', 'gt', 'gte', 'order'];
                break;
            case 'boolean':
                return ['is', 'is_not', 'order'];
                break;
            case 'geometry':
                return ['is', 'is_not'];
                break;
            default:
                throw new \InvalidArgumentException('Field type "'.$fieldType.'" is not supported!');
        }
    }


    public function isFilterSupportingSingleValue($filterId, $fieldType)
    {
        return true;
    }


    public function isFilterSupportingMultipleValues($filterId, $fieldType)
    {
        if ($fieldType == 'boolean') {
            return false;
        }

        if (in_array($filterId, ['lt', 'lte', 'gt', 'gte'])) {
            return false;
        }

        return true;
    }


    /**
     * @param QueryBuilder                $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param                             $resourceClass
     * @param array                       $filterValues
     * @param string                      $filterName
     * @param                             $fieldType
     * @param                             $fieldName
     * @param                             $filterId
     * @param                             $fieldResourceClass
     * @param array                       $params
     * @param array                       $conditions
     *
     * @throws \Exception
     */
    public function extendQueryBuilderParamsAndConditions(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        $resourceClass,
        array $filterValues,
        string $filterName,
        $fieldType,
        $fieldName,
        $filterId,
        $fieldResourceClass,
        array &$params,
        array &$conditions
    ): void {


        $dqlEntityAlias = $queryBuilder->getRootAliases(
        )[0]; // usually "o" ... for using like "o.fieldName" ind DQL statements

        $associationName = null;
        if ($this->isPropertyNested($fieldName, $resourceClass)) {
            // i.e. $fieldName = rooms.no; $property = rooms_no_is;
            [$associationName, $referencedField] = $this->getPartsOfNestedFieldName($fieldName); // i.e. [rooms, no]
            if (!$this->isPropertyMapped($referencedField, $fieldResourceClass)) {
                // i.e. $fieldResourceClass = Ufz\ApiBase\Entity\Room
                return;
            }

            // Join referenced entity and return alias of referenced entity for using in DQL statements
            // => (override existing $dqlEntityAlias variable, so the following code uses the right alias)
            [$dqlEntityAlias] = $this->addJoinsForNestedProperty(
                $fieldName,
                $dqlEntityAlias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                Join::INNER_JOIN
            );
            // => override field name, so entity-part is removed (i.e. "nameDe" instead of "rooms.nameDe")
            $fieldName = $referencedField;
        }


        $i = -1;
        foreach ($filterValues as $filterValue) {
            $i++;
            $paramId = 'param_'.md5($filterName).'_'.$i;

            // Convert filter value to DateTime object if field type is datetime
            if ($fieldType == 'datetime' && $filterId !== 'order') {
                try {
                    $filterValue = new \DateTime($filterValue);
                } catch (\Exception $e) {
                    throw new \Exception('Filter "'.$fieldName.'" contains an invalid date- or time value!');
                }
            }

            switch ($filterId) {
                case 'is':
                    $param = $filterValue;
                    if ($param !== null && $param !== "null") {
                        $condition = $dqlEntityAlias.'.'.$fieldName.' = :'.$paramId.'';
                    } else {
                        $condition = $dqlEntityAlias.'.'.$fieldName.' is null';
                    }
                    break;
                case 'is_not':
                    $param = $filterValue;
                    if ($param !== null && $param !== "null") {
                        $condition = $associationName !== null
                            ? "$dqlEntityAlias.$fieldName != :$paramId"
                            : "($dqlEntityAlias.$fieldName != :$paramId OR $dqlEntityAlias.$fieldName is null)";
                    } else {
                        $condition = $dqlEntityAlias.'.'.$fieldName.' is not null';
                    }
                    break;
                case 'contains':
                    $param = '%'.mb_strtolower($filterValue).'%';
                    $condition = 'LOWER('.$dqlEntityAlias.'.'.$fieldName.') like :'.$paramId.'';
                    break;
                case 'starts_with':
                    $param = mb_strtolower($filterValue).'%';
                    $condition = 'LOWER('.$dqlEntityAlias.'.'.$fieldName.') like :'.$paramId.'';
                    break;
                case 'ends_with':
                    $param = '%'.mb_strtolower($filterValue);
                    $condition = 'LOWER('.$dqlEntityAlias.'.'.$fieldName.') like :'.$paramId.'';
                    break;
                case 'lt':
                    $param = $filterValue;
                    $condition = $dqlEntityAlias.'.'.$fieldName.' < :'.$paramId.'';
                    break;
                case 'lte':
                    $param = $filterValue;
                    $condition = $dqlEntityAlias.'.'.$fieldName.' <= :'.$paramId.'';
                    break;
                case 'gt':
                    $param = $filterValue;
                    $condition = $dqlEntityAlias.'.'.$fieldName.' > :'.$paramId.'';
                    break;
                case 'gte':
                    $param = $filterValue;
                    $condition = $dqlEntityAlias.'.'.$fieldName.' >= :'.$paramId.'';
                    break;
                case 'external':
                    foreach ($this->externalFilters as $externalFilter) {
                        if ($externalFilter->supports($fieldName)) {
                            $externalFilter->doFiltering($fieldName, $filterValue, $queryBuilder, $queryNameGenerator, $resourceClass);
                        }
                    }
                    return;
                case 'order':
                    if (!in_array(strtolower($filterValue), ['asc', 'desc'])) {
                        throw new BadRequestHttpException('Allowed sorting directions are `asc` and `desc`');
                    }
                    if (!$this->hasAppliedOrder) {
                        $queryBuilder->orderBy($dqlEntityAlias.'.'.$fieldName, $filterValue);
                        $this->hasAppliedOrder = true;
                    } else {
                        $queryBuilder->addOrderBy($dqlEntityAlias.'.'.$fieldName, $filterValue);
                    }
                    return;
                default:
                    throw new \Exception('Filter with ID "'.$filterId.'" is not supported!');
            }

            if (in_array($filterId, ['is', 'is_not']) && ($param === null || $param === "null")) {
                // "is: null" and "is_not: null" are not defining any parameter .. so don't add a parameter
                // string comparision ($param === "null") is used, because REST queries can not define nullable parameter values   
            } else {
                $params[$paramId] = $param;
            }
            $conditions[] = $condition;
        }
    }


    /**
     * @param QueryBuilder $queryBuilder
     * @param array        $params
     */
    protected function presetQueryBuilderParams(QueryBuilder $queryBuilder, array &$params): void
    {
        /** @var Parameter $parameter */
        foreach ($queryBuilder->getParameters() as $parameter) { // preset previous set params            
            $params[$parameter->getName()] = $parameter->getValue();
        }
    }


    /**
     * Gets parts of nested field name.
     * i.e.: "entityname.fieldname"
     *
     * @param $fieldName
     *
     * @return array i.e. ['entityname', 'fieldname']
     */
    public function getPartsOfNestedFieldName($fieldName)
    {
        $pos = strpos($fieldName, '.');
        if (false === $pos) {
            return [null, $fieldName];
        }

        return [substr($fieldName, 0, $pos), substr($fieldName, $pos + 1)];
    }

    /**
     * @param string $fieldName
     * @param string $resourceClass
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function isFieldNameReadableByCurrentUser(string $fieldName, string $resourceClass): bool
    {

        if (!$this->security->getUser() instanceof MachineUser && !$this->security->getUser() instanceof HumanUser) {
            return false;
        }

        $readableFields = $this->permissionProvider->getReadableFieldNamesOfEntity($resourceClass,$this->security->getUser());
        if(in_array($fieldName,$readableFields)){
            return true;
        }

        // In GraphQL fuzzySearch is no property of the entity (in REST it is)
        // user who have access to the whole entity can use that filter
        if($this->permissionProvider->hasReadAccessToWholeEntity($resourceClass,$this->security->getUser())){
            return true;
        }

        return false;

    }

    /**
     * @param              $value
     * @param QueryBuilder $queryBuilder
     * @param string       $resourceClass
     *
     * @throws Error
     * @throws \ReflectionException
     */
    protected function handleFuzzySearch($value, QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $resourceClassWithoutNamespace = (new \ReflectionClass($resourceClass))->getShortName();

        $definedFuzzySearchFields = $this->properties['fuzzySearch'];
        if (!is_array($definedFuzzySearchFields)) {
            throw new \Exception('fuzzy search fields must be defined as array');
        }

        // Check if user has permission to use fuzzySearch filter (requires read-access for pseudo property "fuzzysearch" or read access for whole entity)
        if (!$this->isFieldNameReadableByCurrentUser('fuzzySearch', $resourceClass)) {
            throw new AccessDeniedHttpException(
                'Permission denied to use filter "fuzzySearch" of entity "'.$resourceClassWithoutNamespace.'"'
            );
        }

        // Extract fields, that user has permissions for
        $accessibleFuzzySearchFields = [];
        foreach ($definedFuzzySearchFields as $fieldName) {
            if (!$this->isPropertyMapped($fieldName, $resourceClass)) {
                continue;
            }
            if (!$this->isFieldNameReadableByCurrentUser($fieldName, $resourceClass)) {
                continue;
            }
            $accessibleFuzzySearchFields[] = $fieldName;
        }

        /*
         * Define doctrine conditions for selecting data based on the provided filter  
         */
        $params = [];
        $conditions = [];
        $this->presetQueryBuilderParams($queryBuilder, $params);


        $rawSearchTerms = explode(' ', $value);
        $searchTerms = [];
        foreach ($rawSearchTerms as $rawSearchTerm) {
            $rawSearchTerm = trim($rawSearchTerm);
            if (empty($rawSearchTerm)) {
                continue;
            }
            $rawSearchTerm = mb_strtolower($rawSearchTerm);
            $searchTerms[] = $rawSearchTerm;
        }
        $searchTerms = array_unique($searchTerms);


        $dqlEntityAlias = $queryBuilder->getRootAliases(
        )[0]; // usually "o" ... for using like "o.fieldName" ind DQL statements

        if (!empty($accessibleFuzzySearchFields)) {
            foreach ($searchTerms as $i => $searchTerm) {
                $termRelatedConditions = [];

                foreach ($accessibleFuzzySearchFields as $j => $searchField) {
                    $isStringType = in_array(
                        $this->getDoctrineFieldType($searchField, $resourceClass),
                        EntityUtils::DOCTRINE_STRING_TYPES
                    );
                    $paramId = 'fuzzySearch_' . $i . '_' . $j;
                    $params[$paramId] = $isStringType ? "%$searchTerm%" : (float) $searchTerm;

                    $termRelatedConditions[] = $isStringType
                        ? "LOWER($dqlEntityAlias.$searchField) like :$paramId"
                        : "$dqlEntityAlias.$searchField = :$paramId";
                }
                $conditions[] = '('.join(' OR ', $termRelatedConditions).')';
            }
        }

        // Negative condition, if no conditions are provided (i.e. if no search terms are provided or if user has no permissions to access one of the fields, that are required by fuzzySearch filter)
        if (empty($conditions)) {
            $conditions[] = '1 = 2';
        }


        /*
         * Add conditions to query builder
         * => logical combine conditions - depending on using including- or excluding filter
         */
        $queryBuilder
            ->andWhere(join(' AND ', $conditions))
            ->setParameters($params);

    }
}
