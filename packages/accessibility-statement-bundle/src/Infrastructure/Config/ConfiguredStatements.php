<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Config;

use Override;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;

/**
 * The statement written in the configuration, the only version there is; a new version is written there too. While the
 * configuration holds none, no version is published: the page is not found and the footer has no link to it.
 */
final readonly class ConfiguredStatements implements StatementRepository
{
    private ?Statement $statement;

    /**
     * @param ?array<array-key, mixed> $statement the data `StatementReader` reads, checked when the container is built
     */
    public function __construct(?array $statement)
    {
        $this->statement = null === $statement ? null : StatementReader::fromArray($statement);
    }

    #[Override]
    public function latest(): ?Statement
    {
        return $this->statement;
    }

    #[Override]
    public function find(int $version): ?Statement
    {
        return $version === $this->statement?->version ? $this->statement : null;
    }

    #[Override]
    public function publications(int $limit): array
    {
        return $limit > 0 && $this->statement instanceof Statement ? [$this->statement->version => $this->statement->publishedAt] : [];
    }

    #[Override]
    public function writable(): bool
    {
        return false;
    }

    #[Override]
    public function add(Statement $statement): never
    {
        throw StatementNotStored::readOnly();
    }
}
