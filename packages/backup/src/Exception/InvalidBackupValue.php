<?php

declare(strict_types=1);

namespace Phil\Backup\Exception;

use InvalidArgumentException;

/**
 * A value given to the library cannot be used: a key of the wrong length, a label or a media name with characters a
 * file name cannot hold, a rotation that keeps nothing.
 */
final class InvalidBackupValue extends InvalidArgumentException implements BackupError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
