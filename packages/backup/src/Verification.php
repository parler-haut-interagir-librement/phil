<?php

declare(strict_types=1);

namespace Phil\Backup;

use Phil\Backup\Database\DatabaseIdentity;

/**
 * What a restore test proved: the database the backup was restored into and checked, null when none was given, and
 * the number of files of the media archive read whole, null when the backup has none.
 */
final readonly class Verification
{
    public function __construct(
        public ?DatabaseIdentity $restoredInto,
        public ?int $mediaFiles,
    ) {
    }
}
