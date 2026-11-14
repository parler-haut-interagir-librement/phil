<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Integration\Infrastructure;

use Override;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalFeedCheckpoints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL 17, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalAuditRowRepository::class)]
#[CoversClass(DbalAuditLogReader::class)]
#[CoversClass(DbalFeedCheckpoints::class)]
#[CoversClass(AuditTable::class)]
#[Group('mysql')]
final class PostgreSqlStorageTest extends SqlStorageTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) ? $url : null;
    }
}
