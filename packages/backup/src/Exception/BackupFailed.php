<?php

declare(strict_types=1);

namespace Phil\Backup\Exception;

use RuntimeException;

/**
 * A backup, a restore or a restore test could not be carried out: a client tool is missing or failed, a file cannot
 * be read or written, or a backup is damaged.
 */
final class BackupFailed extends RuntimeException implements BackupError
{
    /**
     * @param non-empty-list<string> $candidates
     */
    public static function missing(array $candidates, string $remedy = 'give the path of the tool'): self
    {
        return new self(\sprintf("%s is not installed or not on the PATH: install your database's client tools, or %s.", implode(' or ', $candidates), $remedy));
    }

    public static function failed(string $tool, string $error): self
    {
        return new self(\sprintf('%s failed: %s', $tool, '' === trim($error) ? 'no message.' : trim($error)));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
