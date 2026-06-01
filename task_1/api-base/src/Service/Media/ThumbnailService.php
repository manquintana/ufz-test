<?php declare(strict_types=1);

namespace Ufz\ApiBase\Service\Media;

use Ufz\ApiBase\DTO\MediaItem;
use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ThumbnailService implements ThumbnailInterface
{
    public static function generateThumbnail(
        ImagineInterface $imagine,
        MediaItem $mediaItem,
        string $suffixSeparator,
        string $suffix,
        int $width
    ): MediaItem {

        $nameWithSuffix = (pathinfo($mediaItem->getName(), PATHINFO_FILENAME)) . $suffixSeparator . $suffix;
        $ext = $mediaItem->getFile()->guessExtension();

        if (!$ext) {
            throw new InvalidArgumentException('File requires a valid extension');
        }

        $outputPath = $mediaItem->getFile()->getPath() . DIRECTORY_SEPARATOR . $nameWithSuffix . '.' . $ext;

        $image = $imagine->open($mediaItem->getFile());

        $height = $image->getSize()->getHeight();

        $image->thumbnail(new Box($width, $height))
            ->save($outputPath);

        $media = new MediaItem();
        $media->setFile(new UploadedFile($outputPath, $nameWithSuffix . '.' . $ext, $mediaItem->getFile()->getMimeType()));
        $media->setName($nameWithSuffix);

        return $media;
    }

    private array $sizes;
    private ImagineInterface $imagine;
    private string $suffixSeparator;

    public function __construct(ImagineInterface $imagine, string $suffixSeparator, array $sizes)
    {
        $this->imagine = $imagine;
        $this->sizes = $sizes;
        $this->suffixSeparator = $suffixSeparator;
    }

    public function generate(MediaItem $file): array
    {
        return array_map(function ($parameter) use ($file) {
            [$suffix, $width] = $parameter;
            return [$suffix, self::generateThumbnail($this->imagine, $file, $this->suffixSeparator, $suffix, $width)];
        }, $this->sizes);
    }
}
