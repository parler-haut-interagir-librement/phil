<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineUpdatePlanRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineSiteRepository::class)]
#[CoversClass(DoctrineSnapshotRepository::class)]
#[CoversClass(DoctrineConfigurationPushRepository::class)]
#[CoversClass(DoctrineUpdatePlanRepository::class)]
#[CoversClass(DoctrineJournalRepository::class)]
#[CoversClass(DoctrineKeyRotationRepository::class)]
final class SqliteRepositoryTest extends DoctrineRepositoryTestCase
{
    #[Override]
    protected function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
