<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Media;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;

/**
 * A medium of phil/media-bundle as the image of a shared page: its absolute signed address, its replacement text, its
 * size and its media type, as its schema.org data gives them; the media type only when it is an image's.
 */
final readonly class MediaShareImages
{
    public function __construct(
        private MediaExtension $media,
    ) {
    }

    /**
     * @return array{url: int|string|null, alt: int|string|null, width: int|string|null, height: int|string|null, type: string|null}
     */
    public function image(Media $media): array
    {
        $schema = $this->media->schema($media);
        $type = $schema['encodingFormat'] ?? null;

        return [
            'url' => $schema['contentUrl'] ?? null,
            'alt' => $schema['description'] ?? null,
            'width' => $schema['width'] ?? null,
            'height' => $schema['height'] ?? null,
            'type' => \is_string($type) && str_starts_with($type, 'image/') ? $type : null,
        ];
    }
}
