<?php

namespace Ufz\ApiBase\Tests\Utility;

use Closure;
use Symfony\Component\HttpFoundation\File\File;

class FilesUtility
{

    /**
     * Create files, apply f to the files, afterwards delete the files.
     */
    public static function executeWithTempFiles(Closure $createFiles, Closure $f)
    {
        try {
            $uploadedFiles = $createFiles();

            $f($uploadedFiles);

        } finally {
            array_walk($uploadedFiles, function (File $file) {
                $path = $file->getPathname();
                if (file_exists($path)) {
                    unlink($path);;
                }
            });
        }
    }

}
