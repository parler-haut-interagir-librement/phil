<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;

/**
 * The published versions of the statement: read by the page, added to by the command that regenerates it.
 */
interface StatementRepository
{
    /**
     * The version in force: the one of the highest number, or `null` when none is published.
     */
    public function latest(): ?Statement;

    public function find(int $version): ?Statement;

    /**
     * When each version was published, by number, the newest first, at most `$limit` of them: what a list of the
     * versions shows, without reading each statement.
     *
     * @return array<int, DateTimeImmutable>
     */
    public function publications(int $limit): array;

    /**
     * Whether `add()` keeps a version: a statement written in the configuration is read only.
     */
    public function writable(): bool;

    /**
     * Publishes a version, never changed afterwards.
     *
     * @throws StatementNotStored when the source is read only, or the number is taken
     */
    public function add(Statement $statement): void;
}
