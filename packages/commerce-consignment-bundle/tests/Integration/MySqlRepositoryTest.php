<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Integration;

use Override;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine repository on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DoctrineLedgerRepository::class)]
#[CoversClass(DoctrineConsignorRepository::class)]
#[CoversClass(DoctrineStatementNumbering::class)]
#[CoversClass(BinaryKeysListener::class)]
final class MySqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }

    public function testTheKeysHaveTheBinaryCollation(): void
    {
        $mapping = $this->entityManager()->getClassMetadata(StockLayer::class)->getFieldMapping('consignor');

        self::assertSame('utf8mb4_bin', $mapping->options['collation'] ?? null);
    }
}
