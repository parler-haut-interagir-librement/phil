<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use Phil\MediaBundle\Domain\Media\AiDisclosure;
use Phil\MediaBundle\Domain\Model\Media;

/**
 * A medium as schema.org reads it: an `ImageObject` for an image, a `MediaObject` otherwise, with its URL, name,
 * description, credit, license as an SPDX URL, format, dimensions, when it last changed, and what it owes to
 * artificial intelligence as an IPTC digital source type — the one declared, or the closest to its AI disclosure —: a
 * generated image says so to the engines that read it.
 */
final class MediaSchema
{
    private const string IPTC = 'https://cv.iptc.org/newscodes/digitalsourcetype/';

    /**
     * @return array<string, int|string>
     */
    public static function of(Media $media, string $url): array
    {
        $file = $media->file();
        $license = $media->licenseId();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $file->isImage() ? 'ImageObject' : 'MediaObject',
            'contentUrl' => $url,
            'name' => $media->title() ?? $file->filename,
            'description' => $media->alt(),
            'creditText' => $media->credit(),
            'license' => 'all-rights-reserved' === $license ? null : 'https://spdx.org/licenses/'.$license,
            'encodingFormat' => $file->mimeType,
            'width' => $file->width,
            'height' => $file->height,
            'dateModified' => $media->updatedAt()->format(\DATE_ATOM),
            'digitalSourceType' => null !== $media->digitalSourceType() ? self::IPTC.$media->digitalSourceType() : match ($media->aiDisclosure()) {
                AiDisclosure::Generated => self::IPTC.'trainedAlgorithmicMedia',
                AiDisclosure::Assisted => self::IPTC.'compositeWithTrainedAlgorithmicMedia',
                AiDisclosure::None => null,
            },
        ], static fn (int|string|null $value): bool => null !== $value);
    }
}
