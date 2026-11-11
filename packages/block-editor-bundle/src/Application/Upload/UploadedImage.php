<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Upload;

/**
 * Where an uploaded image is now: the address the image block shows, its size when known, and what else the
 * application keeps in the block's `file` — an identifier of its media library, a credit.
 */
final readonly class UploadedImage
{
    /**
     * @param array<string, scalar> $extra fields added to the block's `file`, beside `url`, `width` and `height`
     */
    public function __construct(
        public string $url,
        public ?int $width = null,
        public ?int $height = null,
        public array $extra = [],
    ) {
    }

    /**
     * The `file` of an image block, as the Editor.js image tool keeps it.
     *
     * @return array<string, scalar>
     */
    public function file(): array
    {
        return [...$this->extra, 'url' => $this->url, ...array_filter(['width' => $this->width, 'height' => $this->height], static fn (?int $size): bool => null !== $size)];
    }
}
