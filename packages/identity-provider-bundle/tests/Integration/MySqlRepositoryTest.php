<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Integration;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine stores on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DoctrineAccountRepository::class)]
final class MySqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
