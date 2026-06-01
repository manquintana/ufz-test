<?php

namespace Ufz\ApiBase\Interfaces;

interface LoggedUserAwareInterface
{
    /**
     * @return mixed
     */
    public function getLoggedUser();
}
