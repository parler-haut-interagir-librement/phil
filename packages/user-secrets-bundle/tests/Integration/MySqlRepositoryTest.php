<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Integration;

use Override;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * MySQL 8, through `pdo_mysql`: `phil-dev services test` gives it `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DoctrineStoredSecretRepository::class)]
#[CoversClass(StoredSecret::class)]
#[Group('mysql')]
final class MySqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) ? $url : null;
    }
}
