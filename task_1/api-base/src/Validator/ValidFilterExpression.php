<?php declare(strict_types=1);

namespace Ufz\ApiBase\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Class constraint for validating FilterHistoryInterface expressions.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidFilterExpression extends Constraint
{
    public string $message = 'The condition is not in correct format';

    /**
     * @return array|string
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * @return string
     */
    public function validatedBy(): string
    {
        return ValidFilterExpressionValidator::class;
    }
}
