<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Integration;

use Override;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineUpdatePlanRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Doctrine stores on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DoctrineSiteRepository::class)]
#[CoversClass(DoctrineSnapshotRepository::class)]
#[CoversClass(DoctrineConfigurationPushRepository::class)]
#[CoversClass(DoctrineUpdatePlanRepository::class)]
#[CoversClass(DoctrineJournalRepository::class)]
#[CoversClass(DoctrineKeyRotationRepository::class)]
final class PostgreSqlRepositoryTest extends ServerRepositoryTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
