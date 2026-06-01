<?php

namespace Ufz\ApiBase\Service\Media;

use Symfony\Component\HttpFoundation\File\File;

/**
 * Test whether a file is supported by checking its file ending.
 */
class SupportedFilesService implements SupportedFilesInterface
{

    private array $supportedFileEndings;

    public function __construct(array $supportedFileEndings)
    {
        $this->supportedFileEndings = array_map('strtolower', $supportedFileEndings);
    }

    public function isSupported(File $file): bool
    {
        return in_array(strtolower($file->guessExtension()), $this->supportedFileEndings);
    }
}
