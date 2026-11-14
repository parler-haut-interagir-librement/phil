<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;

/**
 * The rows of a database, as the anonymization reads and rewrites them.
 *
 * The adapter finds each table's key, gives its rows in batches of its choosing, writes one row's new values by its
 * key, and wraps the whole run in one transaction, so that a failure leaves the database as it was.
 */
interface AnonymizationTarget
{
    /**
     * The columns of the table's primary key.
     *
     * @param non-empty-string $table
     *
     * @return non-empty-list<non-empty-string>
     *
     * @throws AnonymizationRefused when the table has no primary key
     */
    public function keyOf(string $table): array;

    /**
     * Every row of the table, with the key columns and the given columns.
     *
     * Each row is keyed by the names as given, whatever case the database folds them to.
     *
     * @param non-empty-string                 $table
     * @param non-empty-list<non-empty-string> $key
     * @param list<non-empty-string>           $columns
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(string $table, array $key, array $columns): iterable;

    /**
     * Writes the values on the row the key names.
     *
     * @param non-empty-string                               $table
     * @param array<non-empty-string, mixed>                 $key
     * @param non-empty-array<non-empty-string, scalar|null> $values
     */
    public function update(string $table, array $key, array $values): void;

    /**
     * Runs the work in one transaction, committed when it returns and rolled back when it throws.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
