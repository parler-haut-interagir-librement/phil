<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * The anonymization did not start: the database is a protected one, or a table cannot be rewritten row by row.
 * Nothing was written.
 */
final class AnonymizationRefused extends RuntimeException implements PrivacyError
{
    public static function inEnvironment(string $environment): self
    {
        return new self(\sprintf('Anonymization is refused in the "%s" environment: it rewrites personal data for good. Back the database up, restore the backup elsewhere, and anonymize there.', $environment));
    }

    public static function protectedDatabase(DatabaseIdentity $database): self
    {
        return new self(\sprintf('Anonymization is refused on the %s, which the configuration protects.', $database->describe()));
    }

    public static function withoutKey(string $table): self
    {
        return new self(\sprintf('The table "%s" has no primary key, or does not exist: its rows cannot be rewritten one by one.', $table));
    }

    public static function keyColumn(string $table, string $column): self
    {
        return new self(\sprintf('The column "%s" is part of the primary key of "%s": anonymizing it would break the rows that refer to it.', $column, $table));
    }
}
