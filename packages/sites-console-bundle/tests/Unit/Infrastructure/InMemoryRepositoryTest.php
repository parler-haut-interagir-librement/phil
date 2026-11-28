<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryUpdatePlanRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemorySiteRepository::class)]
#[CoversClass(InMemorySnapshotRepository::class)]
#[CoversClass(InMemoryConfigurationPushRepository::class)]
#[CoversClass(InMemoryUpdatePlanRepository::class)]
#[CoversClass(InMemoryJournalRepository::class)]
#[CoversClass(InMemoryKeyRotationRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    private InMemorySiteRepository $sites;

    private InMemorySnapshotRepository $snapshots;

    private InMemoryConfigurationPushRepository $pushes;

    private InMemoryUpdatePlanRepository $plans;

    private InMemoryJournalRepository $journal;

    private InMemoryKeyRotationRepository $rotations;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->snapshots = new InMemorySnapshotRepository();
        $this->pushes = new InMemoryConfigurationPushRepository();
        $this->plans = new InMemoryUpdatePlanRepository();
        $this->journal = new InMemoryJournalRepository();
        $this->rotations = new InMemoryKeyRotationRepository();
    }

    #[Override]
    protected function sites(): SiteRepository
    {
        return $this->sites;
    }

    #[Override]
    protected function snapshots(): SnapshotRepository
    {
        return $this->snapshots;
    }

    #[Override]
    protected function pushes(): ConfigurationPushRepository
    {
        return $this->pushes;
    }

    #[Override]
    protected function plans(): UpdatePlanRepository
    {
        return $this->plans;
    }

    #[Override]
    protected function journal(): JournalRepository
    {
        return $this->journal;
    }

    #[Override]
    protected function rotations(): KeyRotationRepository
    {
        return $this->rotations;
    }

    #[Override]
    protected function clear(): void
    {
    }
}
