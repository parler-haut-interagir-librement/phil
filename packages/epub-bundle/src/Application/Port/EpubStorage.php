<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Port;

use Phil\EpubBundle\Application\Exception\StorageFailed;

/**
 * Where written books are kept: a directory by default, the media library of phil/media-bundle when configured, or a
 * storage of the application.
 */
interface EpubStorage
{
    /**
     * Keeps the bytes of an EPUB file under a relative path, such as `stories/42.epub`.
     *
     * @return string where it is kept: the path in a directory, the identifier of a medium
     *
     * @throws StorageFailed
     */
    public function store(string $path, string $bytes): string;
}
