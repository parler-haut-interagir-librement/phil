<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Integration\Infrastructure\Doctrine;

use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\ContentStorageTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * MySQL 8, through `pdo_mysql`: the platform where a unique index admits several `NULL`, which the
 * sentinel answers.
 *
 *     PHIL_CONTENT_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:3306/probe' vendor/bin/phpunit tests/Integration
 */
#[CoversClass(DoctrineDimensionContentRepository::class)]
#[CoversClass(DimensionContentMappingListener::class)]
#[CoversClass(LocaleType::class)]
#[CoversClass(StageType::class)]
#[CoversClass(VersionType::class)]
#[Group('mysql')]
final class MySqlContentStorageTest extends ContentStorageTestCase
{
    #[Override]
    protected function connectionParameters(): array
    {
        $url = getenv('PHIL_CONTENT_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_CONTENT_TEST_MYSQL_URL to run this test against a real server.');
        }

        return new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
    }
}
