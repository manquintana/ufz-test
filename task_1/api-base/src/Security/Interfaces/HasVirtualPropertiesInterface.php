<?php

namespace Ufz\ApiBase\Security\Interfaces;

/**
 * Some entities might containt properties that are not mapped to ORM (ie. presigned urls for Images
 * that are generated on the fly). These entities must implement HasVirtualProperties interface
 * and specify the array of names for these 'virtual' properties.
 *
 * @package App\Entity
 */
interface HasVirtualPropertiesInterface
{
    /**
     * Array of names of virtual (non - orm mapped) properties.
     *
     * @return array
     */
    public function getVirtualProperties(): array;
}
