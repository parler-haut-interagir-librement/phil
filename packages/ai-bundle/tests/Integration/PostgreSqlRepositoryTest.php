<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Override;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine journal on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineJournalRepository::class)]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
