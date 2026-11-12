<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Twig;

/**
 * One file type of an image, as `<img>` and `<source>` name it: the URL of the format, the narrower widths in a
 * `srcset`, the format's size — so that the page keeps its place before the image arrives — and the media type.
 */
final readonly class ImageCandidates
{
    /**
     * @param int<1, max>|null $width
     * @param int<1, max>|null $height
     */
    public function __construct(
        public string $src,
        public ?string $srcset,
        public ?int $width,
        public ?int $height,
        public string $mimeType,
    ) {
    }
}
