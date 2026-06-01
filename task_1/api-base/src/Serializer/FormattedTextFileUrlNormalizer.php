<?php

namespace Ufz\ApiBase\Serializer;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Ufz\ApiBase\Interfaces\FormattedTextInterface;
use Ufz\ApiBase\Interfaces\ImageRefInterface;
use Ufz\ApiBase\Interfaces\FormattedTextAttachmentInterface;
use Ufz\ApiBase\Service\Media\StorageUrlBuilder;

final class FormattedTextFileUrlNormalizer implements
    NormalizerInterface,
    DenormalizerInterface,
    NormalizerAwareInterface,
    DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    public const ALREADY_NORMALIZED = 'formatted_text_file_url_normalized';

    public function __construct(
        private StorageUrlBuilder $urlBuilder,
        private ManagerRegistry $managerRegistry,
    ) {}

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return ($data instanceof FormattedTextInterface)
            && !($context[self::ALREADY_NORMALIZED] ?? false);
    }

    /**
     * add urls (referenced by imageRef) to image blocks in formatted text
     *
     * @param $object
     * @param string|null $format
     * @param array $context
     * @return array
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $context[self::ALREADY_NORMALIZED] = true;
        /** @var array $normalized */
        $normalized = $this->normalizer->normalize($object, $format, $context);

        if (!isset($normalized['content']) || !json_validate($normalized['content'])) {
            return $normalized;
        }

        $contentArray = json_decode($normalized['content'], true);
        $updatedBlocks = array();
        $blocks = is_array($contentArray) ? ($contentArray['blocks'] ?? null) : null;

        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                switch ($block['type'] ?? null) {
                    case 'image':
                        $block = $this->addUrlToImageFile($block);
                        break;
                    case 'gallery':
                        $block = $this->addUrlsToGalleryFiles($block);
                        break;
                    case 'attachment':
                        $block = $this->addUrlToAttachmentFile($block);
                        break;
                }

                $updatedBlocks[] = $block;
            }
            $contentArray['blocks'] = $updatedBlocks;
            $normalized['content'] = json_encode($contentArray);
        }

        return $normalized;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    /**
     * add url to image file
     *
     * @param array $block
     * @return array
     */
    public function addUrlToImageFile(array $block): array
    {
        $data = $block['data'] ?? null;
        $file = is_array($data) ? ($data['file'] ?? null) : null;
        $imageRefId = is_array($file) ? ($file['imageRefId'] ?? null) : null;
        if (is_int($imageRefId)) {
            /** @var ImageRefInterface $imageRef */
            $imageRef = $this->managerRegistry->getRepository(ImageRefInterface::class)->find($imageRefId);
            if (isset($imageRef)) {
                $url = $this->urlBuilder->build($imageRef->isPublic(), $imageRef->getRef());
                $block['data']['file']['url'] = $url;
            }
        }
        return $block;
    }

    /**
     * add urls to gallery files
     *
     * @param array $data
     * @return array
     */
    public function addUrlsToGalleryFiles(array $block): array
    {
        $data = $block['data'] ?? null;
        $files = is_array($data) ? ($data['files'] ?? null) : null;

        if (!is_array($files)) {
            return $block;
        }

        foreach ($files as &$file) {
            $imageRefId = is_array($file) ? ($file['imageRefId'] ?? null) : null;
            if (is_int($imageRefId)) {
                /** @var ImageRefInterface $imageRef */
                $imageRef = $this->managerRegistry->getRepository(ImageRefInterface::class)->find($imageRefId);
                if (isset($imageRef)) {
                    $url = $this->urlBuilder->build($imageRef->isPublic(), $imageRef->getRef());
                    $file['url'] = $url;
                }
            }
        }

        $block['data']['files'] = $files;
        return $block;
    }

    /**
     * add url to attachment file
     *
     * @param array $block
     * @return array
     */
    public function addUrlToAttachmentFile(array $block): array
    {
        $data = $block['data'] ?? null;
        $file = is_array($data) ? ($data['file'] ?? null) : null;
        $attachmentId = is_array($file) ? ($file['attachmentId'] ?? null) : null;
        if (is_int($attachmentId)) {
            /** @var FormattedTextAttachmentInterface $attachment */
            $attachment = $this->managerRegistry->getRepository(FormattedTextAttachmentInterface::class)->find($attachmentId);
            if (isset($attachment)) {
                // Attachments are always public
                $url = $this->urlBuilder->build(true, $attachment->getRef());
                $block['data']['file']['url'] = $url;
            }
        }
        return $block;
    }
}