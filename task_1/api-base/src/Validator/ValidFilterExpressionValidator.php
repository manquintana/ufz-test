<?php declare(strict_types=1);

namespace Ufz\ApiBase\Validator;

use Ufz\ApiBase\Security\Filter\GenericFieldBasedFilter;
use Doctrine\ORM\EntityManagerInterface;
use Ufz\ApiBase\Validator\ValidFilterExpression;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Ufz\ApiBase\Interfaces\FilterHistoryEntityInterface;

class ValidFilterExpressionValidator extends ConstraintValidator
{
    private GenericFieldBasedFilter $fieldBasedFilter;

    private EntityManagerInterface $entityManager;

    /**
     * ValidFilterExpressionValidator constructor.
     * @param GenericFieldBasedFilter $fieldBasedFilter
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(GenericFieldBasedFilter $fieldBasedFilter, EntityManagerInterface $entityManager)
    {
        $this->fieldBasedFilter = $fieldBasedFilter;
        $this->entityManager = $entityManager;
    }

    /**
     * @param FilterHistoryEntityInterface $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!empty($value) && $value instanceof FilterHistoryEntityInterface) {
            $resourceClass = null;
            try {
                $resourceClass = $value->getClassNameFromType();
            } catch (BadRequestException $exception) {
                $this->context->buildViolation('Unknown type: ' . $value->getType())->addViolation();
                return;
            }

            if ($this->entityManager->getMetadataFactory()->isTransient($resourceClass)) {
                return;
            }

            foreach ($value->getFilter() as $filterName => $filterValue) {
                try {
                    $this->fieldBasedFilter->getFilterDataByFilterName(str_replace('_list', '', $filterName), $resourceClass);
                } catch (\Exception $exception) {
                    $this->context->buildViolation($filterName . ': ' . $exception->getMessage())->addViolation();
                }
            }
        }
    }
}
