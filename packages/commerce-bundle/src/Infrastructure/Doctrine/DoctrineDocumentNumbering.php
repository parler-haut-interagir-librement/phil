<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceBundle\Infrastructure\Doctrine\Entity\DocumentSequence;

/**
 * `DocumentNumbering` on a row per series of `phil_commerce_document_sequence`, locked for writing until the
 * transaction ends: two documents issued at once wait for each other, and a rolled-back document gives its number
 * back. The number is the series' prefix and the sequence padded with zeros: `R-00000001`.
 *
 * The row is always read again once it is locked, so a long-lived entity manager that took a number earlier never
 * reuses its stale copy of the counter. A series' first row is inserted on its own, in a savepoint: when two first
 * issues race, the second one's insert fails on the key, is rolled back to the savepoint, and the issue goes on
 * with the row the first one created, waiting on its lock.
 *
 * The register's types go through `nextIn()`, one series per type's code, under the same lock.
 */
final readonly class DoctrineDocumentNumbering implements DocumentNumbering, DocumentSeries
{
    /**
     * @param array<value-of<DocumentKind>, string> $prefixes kind → prefix
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private array $prefixes,
        private int $digits,
    ) {
    }

    #[Override]
    public function next(DocumentKind $kind): string
    {
        return ($this->prefixes[$kind->value] ?? '').str_pad((string) $this->sequence($kind->value), $this->digits, '0', \STR_PAD_LEFT);
    }

    #[Override]
    public function nextIn(DocumentType $type): string
    {
        return $type->format($this->sequence($type->code));
    }

    private function sequence(string $series): int
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new LogicException('A document number is taken inside a transaction, committed or rolled back with the document: wrap the issue in EntityManagerInterface::wrapInTransaction().');
        }

        $sequence = $this->locked($series);

        if (!$sequence instanceof DocumentSequence) {
            $this->createRow($series);
            $sequence = $this->locked($series) ?? throw new LogicException(\sprintf('The document series "%s" has no row once created.', $series));
        }

        return $sequence->next();
    }

    /**
     * The series' row, locked for writing and read from the database, or `null` when it has none yet.
     */
    private function locked(string $series): ?DocumentSequence
    {
        $known = $this->entityManager->getUnitOfWork()->tryGetById($series, DocumentSequence::class);

        if (!$known instanceof DocumentSequence) {
            return $this->entityManager->find(DocumentSequence::class, $series, LockMode::PESSIMISTIC_WRITE);
        }

        // `find()` would only lock a row already in the identity map, and keep its stale counter.
        $this->entityManager->refresh($known, LockMode::PESSIMISTIC_WRITE);

        return $known;
    }

    private function createRow(string $series): void
    {
        $metadata = $this->entityManager->getClassMetadata(DocumentSequence::class);
        $connection = $this->entityManager->getConnection();

        try {
            // Nested in the caller's transaction, so in a savepoint: a duplicate key leaves the transaction usable.
            $connection->transactional(static fn (Connection $connection): int|string => $connection->insert($metadata->getTableName(), [
                $metadata->getColumnName('series') => $series,
                $metadata->getColumnName('last') => 0,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Another transaction created the row first; its lock is taken next.
        }
    }
}
