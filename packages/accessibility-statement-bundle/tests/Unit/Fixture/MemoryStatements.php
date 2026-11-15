<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Fixture;

use Override;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;

/**
 * Versions kept in memory, the newest first.
 */
final class MemoryStatements implements StatementRepository
{
    /**
     * @param list<Statement> $statements the newest first
     */
    public function __construct(
        public array $statements = [],
        private readonly bool $writable = true,
    ) {
    }

    #[Override]
    public function latest(): ?Statement
    {
        return $this->statements[0] ?? null;
    }

    #[Override]
    public function find(int $version): ?Statement
    {
        foreach ($this->statements as $statement) {
            if ($version === $statement->version) {
                return $statement;
            }
        }

        return null;
    }

    #[Override]
    public function publications(int $limit): array
    {
        $publications = [];

        foreach (\array_slice($this->statements, 0, $limit) as $statement) {
            $publications[$statement->version] = $statement->publishedAt;
        }

        return $publications;
    }

    #[Override]
    public function writable(): bool
    {
        return $this->writable;
    }

    #[Override]
    public function add(Statement $statement): void
    {
        if (!$this->writable) {
            throw StatementNotStored::readOnly();
        }

        array_unshift($this->statements, $statement);
    }
}
