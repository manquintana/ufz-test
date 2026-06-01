<?php

namespace Ufz\ApiBase\Security\Utils;

use Ufz\ApiBase\Security\Interfaces\HasVirtualPropertiesInterface;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionProperty;

class EntityUtils
{
    public const DOCTRINE_STRING_TYPES = ['string', 'text'];

    public static function getEntityNameFromFQCN(string $fqcn): string
    {
        return (new ReflectionClass($fqcn))->getShortName();
    }

    public static function getPropertiesOfEntity(string $entityFQCN, EntityManagerInterface $entityManager): array
    {
        $properties = [];
        if (!$entityManager->getMetadataFactory()->isTransient($entityFQCN)) {
            $reflectionProperties = $entityManager->getClassMetadata($entityFQCN)->getReflectionProperties();

            foreach ($reflectionProperties as $reflectionProperty) {
                $properties[] = $reflectionProperty->getName();
            }
        } else {
            // if the class is not managed by Doctrine, we can not use the EntityManager to access the list of properties
            $class = new ReflectionClass($entityFQCN);
            $properties = array_map(fn(ReflectionProperty $property) => $property->getName(), $class->getProperties());
        }

        return (new ReflectionClass($entityFQCN))->implementsInterface(HasVirtualPropertiesInterface::class)
            ? array_merge($properties, (new $entityFQCN)->getVirtualProperties())
            : $properties;
    }
}
