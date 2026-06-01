<?php

namespace Ufz\ApiBase\Serializer;

/**
 * Handles circular references during serialization by returning the entity ID.
 *
 * In API Platform 2.6, circular references were automatically resolved to IRIs.
 * In AP 4.x + Symfony 7, this must be configured explicitly.
 */
final class CircularReferenceHandler
{
    public function __invoke(object $object, string $format, array $context): mixed
    {
        if (method_exists($object, 'getId')) {
            $id = $object->getId();

            return $id !== null ? (string) $id : null;
        }

        return null;
    }
}
