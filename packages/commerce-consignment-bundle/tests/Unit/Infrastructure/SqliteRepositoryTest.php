<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\OptimisticLockException;
use Override;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * The Doctrine repository on SQLite in memory; in a file of its own when a test opens two connections — two requests —
 * on the same tables.
 */
#[CoversClass(DoctrineLedgerRepository::class)]
#[CoversClass(DoctrineConsignorRepository::class)]
#[CoversClass(DoctrineStatementNumbering::class)]
#[CoversClass(BinaryKeysListener::class)]
final class SqliteRepositoryTest extends DoctrineRepositoryTestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        parent::tearDown();
        if (null !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }
    }

    #[Override]
    protected function connection(): Connection
    {
        if (!$this->shared) {
            return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        }

        if (null === $this->path) {
            $path = tempnam(sys_get_temp_dir(), 'phil-consignment-');
            $this->path = false === $path ? throw new RuntimeException('No temporary file for SQLite.') : $path;
        }

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path]);
    }

    public function testALayerChangedSinceItWasReadCannotBeWritten(): void
    {
        $entityManager = $this->entityManager();
        $layer = StockLayer::receive(ReceiptLine::purchased(Fixtures::BOOK, 5, Fixtures::cad(1)), Fixtures::SHELF, 'DN-1', Fixtures::at(9));
        $entityManager->persist($layer);
        $entityManager->flush();
        $entityManager->getConnection()->executeStatement('UPDATE phil_commerce_consignment_layer SET remaining = 4, version = version + 1');

        $layer->take(1);
        $this->expectException(OptimisticLockException::class);
        $entityManager->flush();
    }

    public function testSqliteKeepsTheDefaultCollation(): void
    {
        $mapping = $this->entityManager()->getClassMetadata(StockLayer::class)->getFieldMapping('sku');

        self::assertArrayNotHasKey('collation', $mapping->options ?? []);
    }
}
