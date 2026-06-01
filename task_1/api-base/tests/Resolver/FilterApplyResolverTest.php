<?php

namespace Ufz\ApiBase\Tests\Resolver;

use ApiPlatform\State\Pagination\ArrayPaginator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Ufz\ApiBase\DTO\Filter;
use Ufz\ApiBase\Tests\BaseUnitTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use Ufz\ApiBase\Resolver\FilterApplyResolver;
use Ufz\ApiBase\Service\FilterService;

class FilterApplyResolverTest extends BaseUnitTestCase
{
    /**
     * @var FilterService|MockObject
     */
    private $filterService;

    protected function setUp(): void
    {
        $this->filterService = $this->getMockBuilder(FilterService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInvoke(): void
    {
        $context['args']['filters'] = 'filter_histories/111';
        $collection = new ArrayCollection();

        $this->filterService
            ->expects($this->once())
            ->method('compileCondition')
            ->with($context['args']['filters']);
        $this->filterService
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($this->getMockResult());

        $resolver = new FilterApplyResolver($this->filterService);

        /** @var ArrayCollection $result */
        $result = $resolver->__invoke($collection, $context);
        $this->assertEquals(5, $result->count());
    }

    public function testInvokePacked(): void
    {
        $context['args']['filters'] = 'filter_histories/111';
        $context['args']['packed'] = 'true';
        $collection = new ArrayCollection();

        $this->filterService
            ->expects($this->once())
            ->method('compileCondition')
            ->with($context['args']['filters']);
        $this->filterService
            ->expects($this->once())
            ->method('getResult')
            ->willReturn($this->getMockResult());

        $resolver = new FilterApplyResolver($this->filterService);

        /** @var ArrayCollection $result */
        $result = $resolver->__invoke($collection, $context);
        $this->assertEquals($result->first()['id'], "['1','3','4','2','5']");
    }

    public function testInvokeNoFilters(): void
    {
        $context['args']['filters'] = '';
        $collection = new ArrayCollection();
        $resolver = new FilterApplyResolver($this->filterService);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter must not be empty');

        $resolver->__invoke($collection, $context);
    }

    private function getMockResult(): array
    {
        return [
            (new Filter())->setId(1),
            (new Filter())->setId(3),
            (new Filter())->setId(4),
            (new Filter())->setId(2),
            (new Filter())->setId(5),
        ];
    }
}
