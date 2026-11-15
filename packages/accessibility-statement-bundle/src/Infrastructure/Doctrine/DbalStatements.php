<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The versions in `phil_accessibility_statement`, through DBAL: inserted once, the key on the number refusing a second
 * version of the same number. The statement of the configuration, when there is one, is a version too: in force while
 * the table holds none, listed among the others while the table holds no version of its number. A row whose document
 * no longer reads as a statement is found as no version, and a warning names its number in the log; when it is the
 * newest, no version is in force rather than an older one, which would show a version the site no longer stands by.
 * The moment of each publication is written and read back in UTC.
 */
final readonly class DbalStatements implements StatementRepository
{
    private ?Statement $seed;

    private LoggerInterface $logger;

    /**
     * @param ?array<array-key, mixed> $seed the statement of the configuration
     */
    public function __construct(
        private Connection $connection,
        ?array $seed = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->seed = null === $seed ? null : StatementReader::fromArray($seed);
        $this->logger = $logger ?? new NullLogger();
    }

    #[Override]
    public function latest(): ?Statement
    {
        $row = $this->connection->fetchAssociative('SELECT version, document FROM '.StatementTables::STATEMENTS.' ORDER BY version DESC LIMIT 1');

        if (false === $row) {
            return $this->seed;
        }

        return $this->read($row['version'], $row['document']);
    }

    #[Override]
    public function find(int $version): ?Statement
    {
        $document = $this->connection->fetchOne('SELECT document FROM '.StatementTables::STATEMENTS.' WHERE version = ?', [$version]);

        if (false !== $document) {
            return $this->read($version, $document);
        }

        return $version === $this->seed?->version ? $this->seed : null;
    }

    #[Override]
    public function publications(int $limit): array
    {
        $limit = max(0, $limit);
        $rows = $this->connection->fetchAllKeyValue('SELECT version, published_at FROM '.StatementTables::STATEMENTS.' ORDER BY version DESC LIMIT '.$limit);
        $type = Type::getType(Types::DATETIME_UTC_IMMUTABLE);
        $platform = $this->connection->getDatabasePlatform();
        $publications = [];

        foreach ($rows as $version => $publishedAt) {
            $moment = $type->convertToPHPValue($publishedAt, $platform);

            if ($moment instanceof DateTimeImmutable) {
                $publications[(int) $version] = $moment;
            }
        }

        if ($this->seed instanceof Statement && false === $this->connection->fetchOne('SELECT 1 FROM '.StatementTables::STATEMENTS.' WHERE version = ?', [$this->seed->version])) {
            $publications[$this->seed->version] = $this->seed->publishedAt;
            krsort($publications);
        }

        return \array_slice($publications, 0, $limit, true);
    }

    #[Override]
    public function writable(): bool
    {
        return true;
    }

    #[Override]
    public function add(Statement $statement): void
    {
        try {
            $this->connection->insert(StatementTables::STATEMENTS, [
                'version' => $statement->version,
                'published_at' => $statement->publishedAt,
                'document' => json_encode($statement->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            ], [
                'published_at' => Types::DATETIME_UTC_IMMUTABLE,
            ]);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw StatementNotStored::versionTaken($statement->version, $uniqueConstraintViolationException);
        }
    }

    /**
     * The statement of a row, or none when its document no longer reads; the warning names the version only, never
     * what the document holds.
     */
    private function read(mixed $version, mixed $document): ?Statement
    {
        try {
            return StatementReader::fromJson(\is_string($document) ? $document : '');
        } catch (InvalidStatement) {
            $this->logger->warning('The accessibility statement version {version} does not read as a statement: it is served as no version.', ['version' => $version]);

            return null;
        }
    }
}
