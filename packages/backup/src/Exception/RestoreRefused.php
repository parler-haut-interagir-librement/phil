<?php

declare(strict_types=1);

namespace Phil\Backup\Exception;

use Phil\Backup\Database\DatabaseIdentity;
use RuntimeException;

/**
 * A restore was refused before anything was changed: in production without the variable that allows it, without
 * its confirmation, or without the name of the database typed again.
 */
final class RestoreRefused extends RuntimeException implements BackupError
{
    public static function inProduction(string $environment, string $variable, DatabaseIdentity $database): self
    {
        return new self(\sprintf('A restore is refused in the "%s" environment: set %s to the name of the database, %s, for this run alone.', $environment, $variable, $database->typedName()));
    }

    public static function unconfirmed(DatabaseIdentity $database, string $backup): self
    {
        return new self(\sprintf('Nothing was restored: add --confirm to replace the content of the %s with %s.', $database->describe(), $backup));
    }

    public static function untyped(DatabaseIdentity $database): self
    {
        return new self(\sprintf('Nothing was restored: type the name of the database again, %s, with --database.', $database->typedName()));
    }

    public static function mistyped(DatabaseIdentity $database, string $typed): self
    {
        return new self(\sprintf('Nothing was restored: "%s" is not the name of the database, %s.', $typed, $database->typedName()));
    }
}
