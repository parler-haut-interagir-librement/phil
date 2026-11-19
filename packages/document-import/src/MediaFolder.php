<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Override;

/**
 * The default store: it keeps nothing and gives each image the address of its file name under a prefix, `media/`
 * unless told otherwise; the application writes the bytes of `Imported::$media` there itself.
 */
final readonly class MediaFolder implements MediaStore
{
    public function __construct(
        private string $prefix = 'media/',
    ) {
    }

    #[Override]
    public function store(Media $media): string
    {
        return $this->prefix.$media->filename();
    }
}
