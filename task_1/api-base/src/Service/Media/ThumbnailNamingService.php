<?php


namespace Ufz\ApiBase\Service\Media;

/**
 * Determine the name of thumbnails of an original.
 */
class ThumbnailNamingService
{
    private string $suffixSeparator;
    private array $suffixes;

    public function __construct(string $suffixSeparator, array $sizes)
    {
        $this->suffixSeparator = $suffixSeparator;
        $this->suffixes = array_map(function ($suffixAndWidth) {
            return $suffixAndWidth[0];
        }, $sizes);
    }

    public function getSuffixes(): array
    {
        return $this->suffixes;
    }

    public function getSuffixSeparator(): string
    {
        return $this->suffixSeparator;
    }

    public function thumbnailForFile(string $filename, string $suffix): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return (pathinfo($filename, PATHINFO_FILENAME)) . $this->suffixSeparator . $suffix .
            ($ext != '' ? '.' . $ext : '');
    }

    /**
     * Returns all filenames of thumbnails for a given file.
     * Just returns filenames, ignoring any directories, e.g /uploads/a.jpg returns just a_suffix.jpg.
     */
    public function thumbnailsForFile(string $filename): array
    {
        return array_map(function ($suffix) use ($filename) {
            return $this->thumbnailForFile($filename, $suffix);
        }, $this->suffixes);
    }


}
