<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Integration;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine stores on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineAccountRepository::class)]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
