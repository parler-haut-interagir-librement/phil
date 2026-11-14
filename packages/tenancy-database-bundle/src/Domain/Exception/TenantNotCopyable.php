<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use RuntimeException;

/**
 * A mapping whose rows a move cannot copy table by table: tables that reference each other, or a key of several
 * columns where a tenant's rows are found through one.
 */
final class TenantNotCopyable extends RuntimeException implements DomainError
{
    /**
     * @param list<string> $tables
     */
    public static function cycle(array $tables): self
    {
        return new self(\sprintf('The tenant-scoped tables %s reference each other: a move cannot order their rows.', implode(', ', $tables)));
    }

    public static function compositeKey(string $table): self
    {
        return new self(\sprintf('The table %s reaches its tenant through a key of several columns: a move copies a table reached through one column only.', $table));
    }
}
