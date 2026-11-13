<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\Presets;
use Symfony\Component\Clock\MockClock;

/**
 * The check a page makes before it lets a member sign up, post or invite: one indexed question to the store, on SQLite
 * in memory with a journal of 5,000 past incidents, with no incident in force and with two.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"incident-mode"})
 */
final class IncidentModeBench
{
    private IncidentMode $calm;

    private IncidentMode $storm;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $this->calm = $this->incidents(false);
        $this->storm = $this->incidents(true);
    }

    public function benchCheckWithoutIncident(): void
    {
        $this->calm->isSuspended('signups');
    }

    public function benchCheckDuringTwoIncidents(): void
    {
        $this->storm->isSuspended('uploads');
    }

    private function incidents(bool $inForce): IncidentMode
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (IncidentTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $clock = new MockClock('2026-01-01 00:00:00 UTC');
        $incidents = new IncidentMode(Presets::defaults(), new DbalActivationStore($connection), $clock);
        $actor = Actor::of('bench');

        for ($i = 0; $i < 5_000; ++$i) {
            $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofMinutes(30), $actor);
            $clock->sleep(3_600);
        }

        $incidents->liftExpired(5_000);

        if ($inForce) {
            $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(2), $actor);
            $incidents->start(Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(2), $actor);
        }

        return $incidents;
    }
}
