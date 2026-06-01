<?php

namespace Ufz\ApiBase\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufz\ApiBase\Interfaces\ImageRefInterface;
use Ufz\ApiBase\Interfaces\ThumbnailRefInterface;
use Ufz\ApiBase\Service\Media\StorageUrlBuilder;

final class ImageRefRefNormalizer implements
    NormalizerInterface,
    DenormalizerInterface,
    NormalizerAwareInterface,
    DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    public const ALREADY_NORMALIZED = 'image_ref_ref_normalized';

    public function __construct(private StorageUrlBuilder $urlBuilder) {}

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return ($data instanceof ImageRefInterface || $data instanceof ThumbnailRefInterface)
            && !($context[self::ALREADY_NORMALIZED] ?? false);
    }

    public function normalize($object, string $format = null, array $context = []): array
    {
        $context[self::ALREADY_NORMALIZED] = true;
        /** @var array $normalized */
        $normalized = $this->normalizer->normalize($object, $format, $context);

        if (isset($normalized['ref']) && is_string($normalized['ref'])) {
            $normalized['ref'] = $this->urlBuilder->build($object->isPublic(), $normalized['ref']);
        }

        return $normalized;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return (is_a($type, ImageRefInterface::class, true) ||
                is_a($type, ThumbnailRefInterface::class, true))
            && !($context[self::ALREADY_NORMALIZED] ?? false);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_NORMALIZED] = true;

        if (is_array($data) && isset($data['ref']) && is_string($data['ref'])) {
            $data['ref'] = $this->urlBuilder->strip($data['ref']);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ImageRefInterface::class => false,
            ThumbnailRefInterface::class => false,
        ];
    }
}