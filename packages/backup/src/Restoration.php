<?php

declare(strict_types=1);

namespace Phil\Backup;

/**
 * What a restore wrote: the number of media files written back, and the number of files of the media directories
 * removed because the archive does not hold them, zero unless the restore prunes.
 */
final readonly class Restoration
{
    public function __construct(
        public int $mediaFiles,
        public int $removedFiles,
    ) {
    }
}
