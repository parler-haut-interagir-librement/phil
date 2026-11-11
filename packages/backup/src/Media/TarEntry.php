<?php

declare(strict_types=1);

namespace Phil\Backup\Media;

/**
 * One entry of a tar archive: a directory or a regular file, its path inside the archive, its permission bits, its
 * size in bytes and its modification time as a Unix timestamp.
 */
final readonly class TarEntry
{
    public function __construct(
        public string $name,
        public bool $directory,
        public int $mode,
        public int $size,
        public int $modified,
    ) {
    }
}
