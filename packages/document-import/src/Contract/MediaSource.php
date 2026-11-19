<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Contract;

/**
 * Where the exporter finds the images a document shows: the application reads the bytes behind an image block's
 * address — from its disk, its media library, the media of an import — and never lets the exporter fetch an address
 * itself.
 */
interface MediaSource
{
    /**
     * The bytes of the image at an address, `null` when the application does not hold it.
     */
    public function fetch(string $url): ?string;
}
