<?php declare(strict_types=1);

namespace Ufz\ApiBase\Tests\Validator;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Ufz\ApiBase\Tests\BaseUnitTestCase;
use Ufz\ApiBase\Validator\FilterCombinationValidator;

class FilterCombinationValidatorTest extends BaseUnitTestCase
{
    protected FilterCombinationValidator $filterCombinationValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterCombinationValidator = new FilterCombinationValidator();
    }

    public function testValidateInputNonAllowedCharacter(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Non allowed character.');
        $this->filterCombinationValidator->validateInput('filter_histories/1 AND {filter_histories/2}');
    }

    public function testValidateInputBracketBeforeOperatorAnd(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('filter_histories/1 (AND filter_histories/2)');
    }

    public function testValidateInputBracketBeforeOperatorOr(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('filter_histories/1 (OR filter_histories/2)');
    }

    public function testValidateInputBracketAfterOperatorAnd(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('(filter_histories/1 AND) filter_histories/2');
    }

    public function testValidateInputBracketAfterOperatorOr(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('(filter_histories/1 OR) filter_histories/2');
    }

    public function testValidateInputCountBracketsMismatch(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('filter_histories/1 AND ((filter_histories/2 OR filter_histories/3)');
    }

    public function testValidateInputMatchBracketsMismatch(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Syntax error.');
        $this->filterCombinationValidator->validateInput('filter_histories/1) AND (filter_histories/2 OR filter_histories/3');
    }

    public function testValidateInputOK(): void
    {
        $this->filterCombinationValidator->validateInput('filter_histories/1 AND (filter_histories/2 OR filter_histories/3)');
        // No exception thrown, method returned - validation OK.
        $this->assertTrue(true);
    }

    public function testvalidateTokensStartWithOperator(): void
    {
        $tokens = [
            'AND',
            'filter_histories/1',
            'AND',
            'filter_histories/2'
        ];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Operator must be placed between filters.');
        $this->filterCombinationValidator->validateTokens($tokens);
    }

    public function testvalidateTokensEndsWithOperator(): void
    {
        $tokens = [
            'filter_histories/1',
            'AND',
            'filter_histories/2',
            'AND'
        ];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Operator must be placed between filters.');
        $this->filterCombinationValidator->validateTokens($tokens);
    }

    public function testvalidateTokensNonAllowedToken(): void
    {
        $tokens = [
            'filter_histories/1',
            'AND',
            'something_other_than_filter_history_id'
        ];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid token something_other_than_filter_history_id');
        $this->filterCombinationValidator->validateTokens($tokens);
    }

    public function testvalidateTokensNonAllowedTokenOperator(): void
    {
        $tokens = [
            'filter_histories/1',
            'no_operator',
            'something_other_than_filter_history_id'
        ];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid token no_operator');
        $this->filterCombinationValidator->validateTokens($tokens);
    }

    public function testvalidateTokensNestedOrEndsWithOperator(): void
    {
        $tokens = [
            'filter_histories/1',
            'AND',
            'filter_histories/2',
            [
                'filter_histories/3',
                'OR',
                'filter_histories/4',
                'AND'
            ]
        ];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Operator must be placed between filters.');
        $this->filterCombinationValidator->validateTokens($tokens);
    }

    public function testvalidateTokensNestedOr(): void
    {
        $tokens = [
            'filter_histories/1',
            'AND',
            'filter_histories/2',
            [
                'filter_histories/3',
                'OR',
                'filter_histories/4'
            ]
        ];
        $this->filterCombinationValidator->validateTokens($tokens);
        // No exception thrown, method returned - validation OK.
        $this->assertTrue(true);
    }
}
