<?php declare(strict_types=1);

namespace Ufz\ApiBase\Resolver;

use ApiPlatform\GraphQl\Resolver\QueryCollectionResolverInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Ufz\ApiBase\Service\FilterService;
use Doctrine\Common\Collections\ArrayCollection;

class FilterApplyResolver implements QueryCollectionResolverInterface
{
    private FilterService $filterService;

    /**
     * @param FilterService $filterService
     */
    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @param iterable $collection
     * @param array $context
     * @return iterable
     */
    public function __invoke(iterable $collection, array $context): iterable
    {
        // Parameter 'filters' is mandatory, but here we check if it is empty string
        if (empty($context['args']['filters'])) {
            throw new BadRequestException('Filter must not be empty');
        }
        $this->filterService->compileCondition($context['args']['filters']);

        $collection = new ArrayCollection();
        $packed = $context['args']['packed'] ?? false;

        if ($packed) {
            $packedIds = '';
            foreach ($this->filterService->getResult() as $filterResultRecord) {
                $packedIds .= '\'' . $filterResultRecord->getId() . '\',';
            }
            $collection->add(['id' => '[' . rtrim($packedIds, ',') . ']']);
        } else {
            foreach ($this->filterService->getResult() as $filterResultRecord) {
                $collection->add($filterResultRecord);
            }
        }
        return $collection;
    }
}
