<?php

namespace Ufz\ApiBase\Tests\Service;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter;
use Ufz\ApiBase\Security\User\HumanUser;
use Ufz\ApiBase\Service\FilterService;
use Ufz\ApiBase\Tests\Entity\EntityImplementation;
use Ufz\ApiBase\Tests\Entity\FilterHistoryEntityImplementation;
use Ufz\ApiBase\Tests\BaseUnitTestCase;
use Ufz\ApiBase\Tests\Entity\FilteredEntityRepositoryInterfaceImplementation;
use Ufz\ApiBase\Validator\FilterCombinationValidator;

class FilterServiceTest extends BaseUnitTestCase
{
    /**
     * @var ServiceEntityRepository|MockObject
     */
    private $filterHistoryRepositoryMock;

    /**
     * @var ManagerRegistry|MockObject
     */
    private $managerRegistryMock;

    /**
     * @var GenericFieldBasedFilter|MockObject
     */
    private $genericFieldBasedFilterMock;

    /**
     * @var MockObject|Security
     */
    private $securityMock;

    /**
     * @var FilterCombinationValidator|MockObject
     */
    private $expressionValidatorMock;

    private FilterService $filterService;

    protected function setUp(): void
    {
        $this->managerRegistryMock = $this->getMockBuilder(ManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->genericFieldBasedFilterMock = $this->getMockBuilder(GenericFieldBasedFilter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->filterHistoryRepositoryMock = $this->getMockBuilder(FilteredEntityRepositoryInterfaceImplementation::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->securityMock = $this->getMockBuilder(Security::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expressionValidatorMock = $this->getMockBuilder(FilterCombinationValidator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->filterService = new FilterService(
            $this->expressionValidatorMock,
            $this->managerRegistryMock,
            $this->genericFieldBasedFilterMock,
            $this->filterHistoryRepositoryMock,
            $this->securityMock
        );
    }

    /**
     * @return FilterService|MockObject
     */
    protected function getPartialFilterServiceMock(array $methods)
    {
        return $this->getMockBuilder(FilterService::class)
            ->setConstructorArgs([
                $this->expressionValidatorMock,
                $this->managerRegistryMock,
                $this->genericFieldBasedFilterMock,
                $this->filterHistoryRepositoryMock,
                $this->securityMock
            ])->onlyMethods($methods)
            ->getMock();
    }

    public function testParseConditionsSimplest(): void
    {
        $method = $this->getNonPublicMethod(FilterService::class, 'parseConditions');

        $expression = 'filter_histories/1 AND filter_histories/2';
        $result = $method->invoke($this->filterService, $expression);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('filter_histories/1', ltrim(rtrim($result[0])));
        $this->assertEquals('AND', ltrim(rtrim($result[1])));
        $this->assertEquals('filter_histories/2', ltrim(rtrim($result[2])));
    }

    public function testParseConditionsNestedOr(): void
    {
        $method = $this->getNonPublicMethod(FilterService::class, 'parseConditions');

        $expression = 'filter_histories/1 AND (filter_histories/2 OR filter_histories/3)';
        $result = $method->invoke($this->filterService, $expression);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('filter_histories/1', ltrim(rtrim($result[0])));
        $this->assertEquals('AND', ltrim(rtrim($result[1])));
        $this->assertIsArray($result[2]);
        $this->assertEquals('filter_histories/2', ltrim(rtrim($result[2][0])));
        $this->assertEquals('OR', ltrim(rtrim($result[2][1])));
        $this->assertEquals('filter_histories/3', ltrim(rtrim($result[2][2])));
    }

    public function testParseConditionsSingleFilter(): void
    {
        $method = $this->getNonPublicMethod(FilterService::class, 'parseConditions');

        $expression = 'filter_histories/1';
        $result = $method->invoke($this->filterService, $expression);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('filter_histories/1', ltrim(rtrim($result[0])));
    }

    public function testParseConditionsMultipleFiltersNoOperator(): void
    {
        $method = $this->getNonPublicMethod(FilterService::class, 'parseConditions');

        $expression = 'filter_histories/1 filter_histories/2';
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Expression cannot be compiled (forgot operator?)');
        $method->invoke($this->filterService, $expression);
    }

    public function testTrimTokens(): void
    {
        $tokens = [' token1 ', ' token2', 'token3 ', [' subtoken1', 'subtoken2 ']];
        $method = $this->getNonPublicMethod(FilterService::class, 'trimTokens');
        $method->invokeArgs($this->filterService, array(&$tokens));
        $this->assertCount(4, $tokens);
        $this->assertEquals('token1', $tokens[0]);
        $this->assertEquals('token2', $tokens[1]);
        $this->assertEquals('token3', $tokens[2]);
        $this->assertEquals('subtoken1', $tokens[3][0]);
        $this->assertEquals('subtoken2', $tokens[3][1]);
    }

    public function testInitFilterHistories(): void
    {
        $conditions = [
            'filter_histories/1',
            'AND',
            'filter_histories/2',
            [
                'filter_histories/3',
                'OR',
                'filter_histories/4'
            ]
        ];
        $mockPartialFilterService = $this->getPartialFilterServiceMock(['initFilter']);

        $mockPartialFilterService
            ->expects($this->exactly(4))
            ->method('initFilter')
            ->willReturn(new FilterHistoryEntityImplementation());

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilterHistories');
        $method->invokeArgs($mockPartialFilterService, array(&$conditions));

        $this->assertIsArray($conditions);
        $this->assertCount(4, $conditions);
        $this->assertCount(4, $conditions);
        $this->assertInstanceOf(FilterHistoryEntityImplementation::class, $conditions[0]);
        $this->assertEquals('AND', $conditions[1]);
        $this->assertInstanceOf(FilterHistoryEntityImplementation::class, $conditions[2]);
        $this->assertInstanceOf(FilterHistoryEntityImplementation::class, $conditions[3][0]);
        $this->assertEquals('OR', $conditions[3][1]);
        $this->assertInstanceOf(FilterHistoryEntityImplementation::class, $conditions[3][2]);
    }

    public function testInitFilterInvalidIri(): void
    {
        $filterId = 'invalid IRI string';
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid IRI format: ' . $filterId);

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilter');
        $method->invoke($this->filterService, ($filterId));
    }

    public function testInitFilterNotExists(): void
    {
        $filterId = 'filter_histories/111';
        $this->filterHistoryRepositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(111)
            ->willReturn(null);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter not found: ' . $filterId);

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilter');
        $method->invoke($this->filterService, ($filterId));
    }

    public function testInitFilterAccessDenied(): void
    {
        $filterId = 'filter_histories/111';
        $filterHistory = (new FilterHistoryEntityImplementation());

        $this->filterHistoryRepositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(111)
            ->willReturn($filterHistory);

        $anotherUser = new HumanUser([
            'sub' => 'otherUser',
            'aud' => 'tmdaud',
            'iss' => 'otherUser',
        ]);

        $this->securityMock
            ->expects($this->once())
            ->method('getUser')
            ->with()
            ->willReturn($anotherUser);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied.');

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilter');
        $method->invoke($this->filterService, ($filterId));
    }

    public function testInitFilterInvalidEntityType(): void
    {
        $filterId = 'filter_histories/111';
        $filterHistory = (new FilterHistoryEntityImplementation())
            ->setType('notSupportedEntityImplementation');

        $this->filterHistoryRepositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(111)
            ->willReturn($filterHistory);

        $loggedUser = new HumanUser([
            'sub' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
            'aud' => 'tmdaud',
            'iss' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
        ]);

        $this->securityMock
            ->expects($this->once())
            ->method('getUser')
            ->with()
            ->willReturn($loggedUser);

        $resourceClassProperty = $this->getNonPublicProperty(FilterService::class, 'resourceClass');
        $resourceClassProperty->setValue($this->filterService, EntityImplementation::class);
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Entity type notSupportedEntityImplementation cannot be resolved.');

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilter');
        $method->invoke($this->filterService, $filterId);
    }

    public function testInitFilter(): void
    {
        $filterId = 'filter_histories/111';
        $filterHistory = (new FilterHistoryEntityImplementation())
            ->setType(FilterHistoryEntityImplementation::HISTORY_IMPLEMENTATION);
        $entityManagerMock = $this->getMockBuilder(EntityManagerInterface::class)
            ->getMock();

        $this->managerRegistryMock
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with(EntityImplementation::class)
            ->willReturn($entityManagerMock);
        $entityManagerMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(EntityImplementation::class)
            ->willReturn($this->filterHistoryRepositoryMock);
        $this->filterHistoryRepositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(111)
            ->willReturn($filterHistory);

        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $this->filterHistoryRepositoryMock
            ->expects($this->once())
            ->method('getFilteredEntityQueryBuilder')
            ->with()
            ->willReturn($queryBuilderMock);

        $loggedUser = new HumanUser([
            'sub' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
            'aud' => 'tmdaud',
            'iss' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
        ]);

        $this->securityMock
            ->expects($this->once())
            ->method('getUser')
            ->with()
            ->willReturn($loggedUser);

        $method = $this->getNonPublicMethod(FilterService::class, 'initFilter');
        $method->invoke($this->filterService, ($filterId));

        $resourceClassProperty = $this->getNonPublicProperty(FilterService::class, 'resourceClass');
        $this->assertEquals($filterHistory->getClassNameFromType(), $resourceClassProperty->getValue($this->filterService));
    }

    public function testBuildWhereSimple(): void
    {
        $filterHistory1 = new FilterHistoryEntityImplementation();
        $filterHistory2 = new FilterHistoryEntityImplementation();
        $conditions = [
            $filterHistory1,
            'AND',
            $filterHistory2
        ];
        $sqlConditions1 = ['id = :param1'];
        $sqlParams1 = ['param1' => 111];
        $sqlConditions2 = ['createdAt > :param2'];
        $sqlParams2 = ['param2' => 222];

        $mockPartialFilterService = $this->getPartialFilterServiceMock(['applyFilter']);
        $mockPartialFilterService
            ->expects($this->any())
            ->method('applyFilter')
            ->with($filterHistory1)
            ->willReturnOnConsecutiveCalls([$sqlConditions1, $sqlParams1], [$sqlConditions2, $sqlParams2]);

        $method = $this->getNonPublicMethod(FilterService::class, 'buildWhere');
        list($where, $params) = $method->invokeArgs($mockPartialFilterService, array(&$conditions));

        $this->assertEquals($where, ' id = :param1 AND createdAt > :param2');
        $this->assertIsArray($params);
        $this->assertCount(2, $params);
        $this->assertArrayHasKey('param1', $params);
        $this->assertEquals(111, $params['param1']);
        $this->assertArrayHasKey('param2', $params);
        $this->assertEquals(222, $params['param2']);
    }

    public function testBuildWhereNestedOr(): void
    {
        $filterHistory1 = new FilterHistoryEntityImplementation();
        $filterHistory2 = new FilterHistoryEntityImplementation();
        $filterHistory3 = new FilterHistoryEntityImplementation();
        $filterHistory4 = new FilterHistoryEntityImplementation();
        $conditions = [
            $filterHistory1,
            'AND',
            $filterHistory2,
            'AND',
            [
                $filterHistory3,
                'OR',
                $filterHistory4
            ]
        ];
        $sqlConditions1 = ['id = :param1'];
        $sqlParams1 = ['param1' => 111];
        $sqlConditions2 = ['createdAt > :param2'];
        $sqlParams2 = ['param2' => 222];
        $sqlConditions3 = ['abundance < :param3'];
        $sqlParams3 = ['param3' => 50];
        $sqlConditions4 = ['tempBegin > :param4'];
        $sqlParams4 = ['param4' => 15];

        $mockPartialFilterService = $this->getPartialFilterServiceMock(['applyFilter']);
        $mockPartialFilterService
            ->expects($this->any())
            ->method('applyFilter')
            ->with($filterHistory1)
            ->willReturnOnConsecutiveCalls(
                [$sqlConditions1, $sqlParams1],
                [$sqlConditions2, $sqlParams2],
                [$sqlConditions3, $sqlParams3],
                [$sqlConditions4, $sqlParams4]
            );

        $method = $this->getNonPublicMethod(FilterService::class, 'buildWhere');
        list($where, $params) = $method->invokeArgs($mockPartialFilterService, array(&$conditions));

        $this->assertEquals($where, ' id = :param1 AND createdAt > :param2 AND ( abundance < :param3 OR tempBegin > :param4)');
        $this->assertIsArray($params);
        $this->assertCount(4, $params);
        $this->assertArrayHasKey('param1', $params);
        $this->assertEquals(111, $params['param1']);
        $this->assertArrayHasKey('param2', $params);
        $this->assertEquals(222, $params['param2']);
        $this->assertArrayHasKey('param3', $params);
        $this->assertEquals(50, $params['param3']);
        $this->assertArrayHasKey('param4', $params);
        $this->assertEquals(15, $params['param4']);
    }

    public function testReplaceMultiValueCondition(): void
    {
        $conditions = ['t.id = :param_a_0'];
        $params = ['param_a_0' => ['1', '2']];

        $method = $this->getNonPublicMethod(FilterService::class, 'replaceMultiValueCondition');
        [$replacedConditions, $replacedParams] = $method->invokeArgs($this->filterService, array($conditions, $params));

        $this->assertCount(2, $replacedParams);
        $this->assertEquals('1', $replacedParams['param_a_0_0']);
        $this->assertEquals('2', $replacedParams['param_a_0_1']);
        $this->assertEquals(' (t.id  IN (:param_a_0_0,:param_a_0_1) ) ', $replacedConditions[0]);
    }

    public function testReplaceMultiValueConditionInconsistentCondition(): void
    {
        $conditions = ['t.id = :param_a_0'];
        $params = ['param_a_xxx' => ['1', '2']];

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Multi-value expression cannot be resolved');

        $method = $this->getNonPublicMethod(FilterService::class, 'replaceMultiValueCondition');
        $method->invokeArgs($this->filterService, array($conditions, $params));
    }

    public function testReplaceMultiValueConditionWithNullValue(): void
    {
        $conditions = ['t.id = :param_a_0'];
        $params = ['param_a_0' => ['1', 'null']];

        $method = $this->getNonPublicMethod(FilterService::class, 'replaceMultiValueCondition');
        [$replacedConditions, $replacedParams] = $method->invokeArgs($this->filterService, array($conditions, $params));

        $this->assertCount(1, $replacedParams);
        $this->assertEquals('1', $replacedParams['param_a_0_0']);
        $this->assertNotContains('param_a_0_1', $replacedParams);
        $this->assertEquals(' (t.id  IN (:param_a_0_0)  OR t.id  IS NULL ) ', $replacedConditions[0]);
    }

    public function testProjectSpecificFilterIsApplied()
    {
        $filterHistoryId = 1;
        // necessary mocks unrelated to this test
        {
            $filterHistory = (new FilterHistoryEntityImplementation())
                ->setType(FilterHistoryEntityImplementation::HISTORY_IMPLEMENTATION);
            $this->filterHistoryRepositoryMock
                ->expects($this->once())
                ->method('find')
                ->with($filterHistoryId)
                ->willReturn($filterHistory);
            $loggedUser = new HumanUser([
                'sub' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
                'aud' => 'tmdaud',
                'iss' => FilterHistoryEntityImplementation::MOCKED_USER_ID,
            ]);
            $this->securityMock
                ->expects($this->once())
                ->method('getUser')
                ->with()
                ->willReturn($loggedUser);
            $entityManagerMock = $this->getMockBuilder(EntityManagerInterface::class)
                ->getMock();
            $entityManagerMock
                ->expects($this->once())
                ->method('getRepository')
                ->with(EntityImplementation::class)
                ->willReturn($this->filterHistoryRepositoryMock);
            $this->managerRegistryMock
                ->expects($this->once())
                ->method('getManagerForClass')
                ->with(EntityImplementation::class)
                ->willReturn($entityManagerMock);
        }

        /** @var QueryCollectionExtensionInterface|MockObject $projectSpecificFilterExtension */
        $projectSpecificFilterExtension = $this->createMock(QueryCollectionExtensionInterface::class);
        $filterService = new FilterService(
            $this->expressionValidatorMock,
            $this->managerRegistryMock,
            $this->genericFieldBasedFilterMock,
            $this->filterHistoryRepositoryMock,
            $this->securityMock,
            $projectSpecificFilterExtension
        );
        $projectSpecificFilterExtension->expects(self::once())->method('applyToCollection');
        $conditions = "filter_histories/$filterHistoryId";
        $filterService->compileCondition($conditions);
    }
}
