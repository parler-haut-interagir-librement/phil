<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Integration;

use Override;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine repository on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineLedgerRepository::class)]
#[CoversClass(DoctrineConsignorRepository::class)]
#[CoversClass(DoctrineStatementNumbering::class)]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
