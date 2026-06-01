<?php

namespace Ufz\ApiBase\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class BaseUnitTestCase extends TestCase
{
    /**
     * Enables testing protected and private methods.
     *
     * @param string $classFCQName
     * @param string $methodName
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    protected function getNonPublicMethod(string $classFCQName, string $methodName): ReflectionMethod
    {
        $class = new ReflectionClass($classFCQName);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Enable access of protected property for assertion.
     *
     * @param string $classFCQName
     * @param string $propertyName
     * @return \ReflectionProperty
     * @throws ReflectionException
     */
    protected function getNonPublicProperty(string $classFCQName, string $propertyName): \ReflectionProperty
    {
        $class = new ReflectionClass($classFCQName);
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }
}
