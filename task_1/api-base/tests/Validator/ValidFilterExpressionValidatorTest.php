<?php declare(strict_types=1);

namespace Ufz\ApiBase\Tests\Validator;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Context\ExecutionContext;
use Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter;
use Ufz\ApiBase\Tests\Entity\FilterHistoryEntityImplementation;
use Ufz\ApiBase\Tests\BaseUnitTestCase;
use Ufz\ApiBase\Validator\ValidFilterExpression;
use Ufz\ApiBase\Validator\ValidFilterExpressionValidator;

class ValidFilterExpressionValidatorTest extends BaseUnitTestCase
{
    /**
     * @var GenericFieldBasedFilter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $genericFieldBasedFilterMock;

    private ValidFilterExpressionValidator $validator;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ExecutionContext
     */
    private $context;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|EntityManagerInterface
     */
    private $entityManager;

    protected function setUp(): void
    {
        $this->genericFieldBasedFilterMock = $this
            ->getMockBuilder(GenericFieldBasedFilter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadataFactory = $this->getMockBuilder(ClassMetadataFactory::class)->getMock();
        $metadataFactory->method('isTransient')->willReturn(false);

        $this->entityManager = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $this->entityManager->method('getMetadataFactory')->willReturn($metadataFactory);

        $this->validator = new ValidFilterExpressionValidator($this->genericFieldBasedFilterMock, $this->entityManager);
        $this->context = $this->getMockBuilder(ExecutionContext::class)->disableOriginalConstructor()->getMock();
        $this->validator->initialize($this->context);
    }

    public function testValidate(): void
    {
        $this->genericFieldBasedFilterMock
            ->expects($this->once())
            ->method('getFilterDataByFilterName')
            ->with('some_field_gt')
            ->willReturn('some_field_gt');

        $filterHistory = FilterHistoryEntityImplementation::create(null, 'test filter', 'historyImplementation', ['some_field_gt' => 10]);
        $this->validator->validate($filterHistory, new ValidFilterExpression());
    }

    public function testValidateUnknownType(): void
    {
        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->with('Unknown type: nonExistingType');

        $filterHistory = FilterHistoryEntityImplementation::create(null, 'test filter', 'nonExistingType', ['some_field_gt' => 10]);
        $this->validator->validate($filterHistory, new ValidFilterExpression());
    }

    public function testValidateNonExistingField(): void
    {
        $this->genericFieldBasedFilterMock
            ->expects($this->once())
            ->method('getFilterDataByFilterName')
            ->with('nonExistingField')
            ->willThrowException(new \InvalidArgumentException('filter name not found'));

        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->with('nonExistingField: filter name not found');

        $filterHistory = FilterHistoryEntityImplementation::create(null, 'test filter', 'historyImplementation', ['nonExistingField' => 10]);
        $this->validator->validate($filterHistory, new ValidFilterExpression());
    }
}
