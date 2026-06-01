<?php

namespace Ufz\ApiBase;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApiBaseBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
