<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Ufz\ApiBase\Interfaces\FilteredEntityRepositoryInterface;
use Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Ufz\ApiBase\Interfaces\FilterHistoryEntityInterface;
use Ufz\ApiBase\Validator\FilterCombinationValidator;

/**
 * Service that provides filter applying and combining facilities.
 * @package Ufz\ApiBase\Service
 */
class FilterService
{
    public const OPERATORS = ['AND', 'OR'];

    protected GenericFieldBasedFilter $fieldBasedFilter;

    protected ManagerRegistry $managerRegistry;

    protected ServiceEntityRepositoryInterface $filterHistoryRepository;

    protected FilteredEntityRepositoryInterface $entityRepository;

    protected ?string $resourceClass = null;

    protected Security $security;

    protected ?QueryBuilder $queryBuilder;

    private FilterCombinationValidator $expressionValidator;

    private bool $strictTypes = true;

    private ?QueryCollectionExtensionInterface $projectSpecificQueryExtension;

    /**
     * @param FilterCombinationValidator $expressionValidator
     * @param ManagerRegistry $managerRegistry
     * @param GenericFieldBasedFilter $fieldBasedFilter
     * @param ServiceEntityRepositoryInterface $filterHistoryRepository
     * @param Security $security
     * @param QueryCollectionExtensionInterface|null $projectSpecificQueryExtension optional queryExtension used for
     * project-specific filtering, e.g filtering by group membership.
     */
    public function __construct(
        FilterCombinationValidator $expressionValidator,
        ManagerRegistry $managerRegistry,
        GenericFieldBasedFilter $fieldBasedFilter,
        ServiceEntityRepositoryInterface $filterHistoryRepository,
        Security $security,
        ?QueryCollectionExtensionInterface $projectSpecificQueryExtension = null
    )
    {
        $this->expressionValidator = $expressionValidator;
        $this->fieldBasedFilter = $fieldBasedFilter;
        $this->managerRegistry = $managerRegistry;
        $this->filterHistoryRepository = $filterHistoryRepository;
        $this->security = $security;
        $this->projectSpecificQueryExtension = $projectSpecificQueryExtension;
    }

    /**
     * @param string $jsonConditions
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function compileCondition(string $jsonConditions): void
    {
        $this->expressionValidator->validateInput($jsonConditions);

        $conditions = $this->parseConditions($jsonConditions);
        $this->trimTokens($conditions);
        $this->expressionValidator->validateTokens($conditions);

        $this->initFilterHistories($conditions);

        list($sqlConditions, $sqlParams) = $this->buildWhere($conditions);
        if (!empty($sqlConditions)) {
            $this->queryBuilder->andWhere($sqlConditions)->setParameters($sqlParams);
        }
        $this->queryBuilder->addCriteria($this->entityRepository->addAdditionalCriteria());

        if ($this->projectSpecificQueryExtension){
            $this->applyQueryExtension($this->queryBuilder, $this->projectSpecificQueryExtension, $this->resourceClass);
        }
    }

    private function applyQueryExtension(
        QueryBuilder $queryBuilder,
        QueryCollectionExtensionInterface $queryCollectionExtension,
        string $resourceClass
    )
    {
        $queryNameGenerator = new QueryNameGenerator();
        $queryCollectionExtension->applyToCollection($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    /**
     * @return array
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getResult(): array
    {
        return $this->queryBuilder->getQuery()->getResult();
    }

    /**
     * Returns the QueryBuilder containing the compiled filter conditions.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if ($this->queryBuilder == null) {
            throw new LogicException(
                'FilterService.queryBuilder is null. Initialize it with a call to compileCondition().'
            );
        }
        return $this->queryBuilder;
    }


    /**
     * @param string $jsonConditions
     * @return array
     */
    protected function parseConditions(string $jsonConditions): array
    {
        $operandsRegex = '/(AND|OR)(?![^\(]*\))/';
        $operands = preg_split(
            $operandsRegex,
            $jsonConditions,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        foreach ($operands as &$operand) {
            if ($operand === $jsonConditions && substr_count($operand, 'filter_histories') > 1) {
                throw new BadRequestException('Expression cannot be compiled (forgot operator?)');
            }
            $enclosingBrackets = '/(^[ ]*[\(])|([\)][ ]*$)/';
            if (preg_match($enclosingBrackets, $operand)) {
                $operand = $this->parseConditions(
                    preg_split($enclosingBrackets, $operand, -1, PREG_SPLIT_NO_EMPTY)[0]
                );
            }
        }
        return $operands;
    }

    /**
     * @param array $tokens
     */
    public function trimTokens(array &$tokens): void
    {
        array_walk($tokens, function(&$elem) {
            if (is_array($elem)) {
                $this->trimTokens($elem);
            } else {
                $elem = rtrim(ltrim($elem));
            }
        });
    }

    /**
     * strictTypes is true by default, the FilterService will only allow filtering the type of entity it encounters in
     * its first call to compileConditions.
     * Subsequent compilations with another entity types will throw exceptions.
     * If set to false, subsequent compilations with other types are allowed.
     */
    public function setStrictTypes(bool $strict): self
    {
        $this->strictTypes = $strict;
        return $this;
    }

    /**
     * @param array $conditions
     */
    protected function initFilterHistories(array &$conditions): void
    {
        foreach ($conditions as &$condition) {
            if (!is_array($condition)) {
                if (!in_array(strtoupper($condition), self::OPERATORS)) {
                    $condition = $this->initFilter($condition);
                }
            } else {
                $this->initFilterHistories($condition);
            }
        }
    }

    /**
     * @param string $idIri
     * @return FilterHistoryEntityInterface
     */
    protected function initFilter(string $idIri): FilterHistoryEntityInterface
    {
        $tokens = explode('/', $idIri);
        $id = (int) $tokens[count($tokens) - 1];
        if ($id === 0 || $tokens[0] != 'filter_histories') {
            throw new BadRequestException(sprintf('Invalid IRI format: %s', $idIri));
        }

        /** @var FilterHistoryEntityInterface $filterHistory */
        $filterHistory = $this->filterHistoryRepository->find($id);
        if ($filterHistory === null) {
            throw new BadRequestException(sprintf('Filter not found: %s', $idIri));
        }
        if ($filterHistory->getUserId() !== $this->security->getUser()->getUserIdentifier()) {
            throw new AccessDeniedException('Access denied.');
        }

        if ($this->resourceClass === null || !$this->strictTypes) {
            $this->resourceClass = $filterHistory->getClassNameFromType();

            $manager = $this->managerRegistry->getManagerForClass($this->resourceClass);
            $this->entityRepository = $manager->getRepository($this->resourceClass);
            $this->queryBuilder = $this->entityRepository->getFilteredEntityQueryBuilder();
        } else if ($this->resourceClass != $filterHistory->getClassNameFromType()) {
            throw new BadRequestException(
                sprintf(
                    'Incompatible types %s and %s',
                    $this->resourceClass,
                    $filterHistory->getClassNameFromType()
                )
            );
        }
        return $filterHistory;
    }

    /**
     * @param array $conditions
     * @return array
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    protected function buildWhere(array $conditions): array
    {
        $where = '';
        $params = [];
        foreach ($conditions as $i => $condition) {
            $operator = isset($conditions[$i - 1]) && is_string($conditions[$i - 1])
                ? strtoupper($conditions[$i - 1])
                : null;
            if ($i == 0 || in_array($operator, self::OPERATORS)) {
                if ($condition instanceof FilterHistoryEntityInterface) {
                    list($sqlConditions, $sqlParams) = $this->applyFilter($condition);
                    if (empty(str_replace(' ', '', $sqlConditions))) {
                        continue;
                    }
                    $oneFilterWhere = implode(' AND ', $sqlConditions);
                    $where .= ($i != 0 ? sprintf(' %s ', $operator) : ' ') . $oneFilterWhere;
                    $params = $params + $sqlParams;
                } else if (is_array($condition)) {
                    // drop deeper
                    list($nextWhere, $nextParams) = $this->buildWhere($condition);
                    $where .= sprintf(' %s (%s)', $operator, $nextWhere);
                    $params = $params + $nextParams;
                }
            }
        }
        return [$where, $params];
    }

    /**
     * @param FilterHistoryEntityInterface $filterHistory
     * @return array[]
     * @throws \Exception
     */
    protected function applyFilter(FilterHistoryEntityInterface $filterHistory): array
    {
        $queryNameGenerator = new QueryNameGenerator();
        $params = [];
        $conditions = [];
        foreach ($filterHistory->getFilter() as $filterName => $filterValue) {
            $multiValue = false;
            if (str_contains($filterName, '_list')) {
                $filterName = str_replace('_list', '', $filterName);
                $multiValue = true;
            }
            $fieldName = $this->fieldBasedFilter->getFieldNameByFilterName($filterName, $this->resourceClass);
            $fieldType = $this->fieldBasedFilter->getFieldTypeByFilterName($filterName, $this->resourceClass);
            $filterId = $this->fieldBasedFilter->getFilterIdByFilterName($filterName, $this->resourceClass);
            $fieldResourceClass = $this->fieldBasedFilter->getFieldResourceClassByFilterName($filterName, $this->resourceClass);

            $this->fieldBasedFilter->extendQueryBuilderParamsAndConditions(
                $this->queryBuilder,
                $queryNameGenerator,
                $this->resourceClass,
                [$filterValue],
                $filterName . '_' . $filterHistory->getId(),
                $fieldType,
                $fieldName,
                $filterId,
                $fieldResourceClass,
                $params,
                $conditions
            );

            if ($multiValue) {
                list($conditions, $params) = $this->replaceMultiValueCondition($conditions, $params);
            }
        }
        return [$conditions, $params];
    }

    /**
     * If params are array and operator '=', replace condition with IN expression.
     */
    protected function replaceMultiValueCondition(array $conditions, array $params): array
    {
        // $params is normally array with single element, as it is called for each filter
        $lastParam = end($params);
        $lastParamName = key($params);

        if (is_array($lastParam)) {
            $newParams = [];
            $i = 0;
            foreach ($lastParam as $key => $lastParamValue) {
                if (strtolower($lastParamValue) === 'null') {
                    continue;
                }
                $newParams[$lastParamName . '_' . $i++] = $lastParamValue;
            }
            $matches = array_filter($conditions, function ($elem) use ($lastParamName) {
                return preg_match("/\b$lastParamName\b/i", $elem);
            });
            if (count($matches) == 0) {
                throw new BadRequestException('Multi-value expression cannot be resolved');
            }

            // Params are unique - there must be only 1 match
            $match = reset($matches);
            $operands = explode('=', $match);
            if (count($operands) < 2) {
                throw new BadRequestException('Multi-value expression cannot be resolved');
            }

            // If NULL is in params, then it needs to be combined with OR condition
            $orClause = '';
            foreach ($lastParam as $paramValue) {
                if (strtolower($paramValue) === 'null') {
                    $orClause .= ' OR ' . $operands[0] . ' IS NULL ';
                }
            }

            $inClause = $operands[0] . ' IN (';
            $newParamNames = array_keys($newParams);
            array_walk($newParamNames, function (&$value) {
                $value = ':' . $value;
            });
            $inClause .= implode(',', $newParamNames);
            $inClause .= ') ';

            array_pop($conditions);
            unset($params[$lastParamName]);
            $conditions[] = ' (' . $inClause . $orClause . ') ';
            $params = $params + $newParams;

        }
        return [$conditions, $params];
    }
}
