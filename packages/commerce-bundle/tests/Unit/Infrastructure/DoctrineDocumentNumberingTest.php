<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use LogicException;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\Entity\DocumentSequence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineDocumentNumbering::class)]
final class DoctrineDocumentNumberingTest extends TestCase
{
    /**
     * The first number of a series creates its row, at zero, in a savepoint, then reads it locked.
     */
    public function testTheFirstNumberOfASeriesCreatesItsRowAtZero(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $insert): mixed => $insert($connection));
        $connection->expects($this->once())
            ->method('insert')
            ->with('phil_commerce_document_sequence', ['series' => 'credit_note', 'last' => 0])
            ->willReturn(1);

        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('tryGetById')->willReturn(false);

        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('phil_commerce_document_sequence');
        $metadata->method('getColumnName')->willReturnArgument(0);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($metadata);
        $entityManager->method('find')->willReturnOnConsecutiveCalls(null, new DocumentSequence('credit_note'));

        self::assertSame('CN-00000001', new DoctrineDocumentNumbering($entityManager, ['credit_note' => 'CN-'], 8)->next(DocumentKind::CreditNote));
    }

    /**
     * A series whose row cannot be read even once it was created, by a database that ignores the insert or a table
     * another process empties, fails with its name rather than with an error on a missing counter.
     */
    public function testASeriesWithoutARowOnceCreatedFails(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);

        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('tryGetById')->willReturn(false);

        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('phil_commerce_document_sequence');
        $metadata->method('getColumnName')->willReturnArgument(0);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($metadata);
        $entityManager->method('find')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The document series "credit_note" has no row once created.');

        new DoctrineDocumentNumbering($entityManager, [], 8)->next(DocumentKind::CreditNote);
    }
}
