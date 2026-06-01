<?php

namespace Ufz\ApiBase\Service\Media;

use Symfony\Component\HttpFoundation\File\File;

interface SupportedFilesInterface
{

    /**
     * Returns true, if the file is supported.
     * @param File $file to check
     * @return bool whether file is supported
     */
    public function isSupported(File $file): bool;

}
