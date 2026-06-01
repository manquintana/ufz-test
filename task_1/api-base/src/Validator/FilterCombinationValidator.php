<?php declare(strict_types=1);

namespace Ufz\ApiBase\Validator;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Ufz\ApiBase\Service\FilterService;

/**
 * Not a constraint validator, just a regular validating logic for filter combination expressions.
 *
 * @package Ufz\ApiBase\Validator
 */
class FilterCombinationValidator
{
    /**
     * @param string $input
     */
    public function validateInput(string $input): void
    {
        if (preg_match('/[^a-zA-Z0-9_\/(). ]+/', $input)) {
            throw new BadRequestHttpException('Non allowed character.');
        }
        if (preg_match('/((\(AND)|(AND\))|(\(OR)|(OR\)))/', $input)) {
            throw new BadRequestHttpException('Syntax error.');
        }

        //count brackets
        $openBracketsCount = substr_count($input, '(');
        if ($openBracketsCount !== substr_count($input, ')')) {
            throw new BadRequestHttpException('Syntax error.');
        }

        //match brackets
        $paired = 0;
        $position = 0;
        $backwardPosition = strlen($input) - 1;
        while ($position < strlen($input)) {
            $openBracketPosition = strpos($input, '(', $position);
            if ($openBracketPosition === false) {
                break;
            }
            for ($i = $backwardPosition; $i >= 0; $i--) {
                if ($input[$i] == ')') {
                    if ($i < $openBracketPosition) {
                        throw new BadRequestHttpException('Syntax error.');
                    } else {
                        $paired++;
                        $position = $openBracketPosition + 1;
                        $backwardPosition = $i - 1;
                        break;
                    }
                }
            }
            $position++;
        }
        if ($paired != $openBracketsCount) {
            throw new BadRequestHttpException('Syntax error.');
        }
    }

    /**
     * @param array $tokens
     */
    public function validateTokens(array $tokens): void
    {
        $firstToken = $tokens[0] ?? null;
        $lastToken = $tokens[count($tokens) - 1] ?? null;
        if (in_array($firstToken, FilterService::OPERATORS)
            || !is_array($lastToken) && in_array($lastToken, FilterService::OPERATORS)
        ) {
            throw new BadRequestHttpException('Operator must be placed between filters.');
        }
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $this->validateTokens($token);
            } else {
                $isFilterHistory = preg_match('/\w*filter_histories\/\d+\/*/', $token);
                $isOperator = in_array(strtoupper($token), FilterService::OPERATORS);
                if (!$isFilterHistory && !$isOperator) {
                    throw new BadRequestHttpException('Invalid token ' . $token);
                }
            }
        }
    }
}
