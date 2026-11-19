<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Integration;

use Override;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL 17, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineStoredSecretRepository::class)]
#[CoversClass(StoredSecret::class)]
#[Group('postgresql')]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) ? $url : null;
    }
}
