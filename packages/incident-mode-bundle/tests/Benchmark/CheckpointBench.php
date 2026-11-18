<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Generator;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\NoEstablishedMembers;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Member;
use Symfony\Component\Clock\MockClock;

/**
 * The checkpoint a page passes before a sign-up, and the banner of every page, on SQLite in memory with a journal of
 * 5,000 past incidents: no incident in force, the common case, and two in force.
 */
final class CheckpointBench
{
    /** The last answer, kept so that the call is not left unused. */
    public ?Suspension $suspension = null;

    /** @var list<Suspension> */
    public array $suspensions = [];

    /** @var array<string, Checkpoint> */
    private array $checkpoints = [];

    public function __construct()
    {
        foreach (['quiet' => 0, 'two incidents' => 2] as $case => $inForce) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
            $connection->createSchemaManager()->createTable(IncidentTables::table());
            $store = new DbalActivationStore($connection);
            $clock = new MockClock('2026-01-01 00:00 UTC');
            $incidents = new IncidentMode(Presets::defaults(), $store, $clock);

            for ($past = 0; $past < 5_000; ++$past) {
                $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofMinutes(30), Actor::of('ops-1'));
                $store->lift($activation->id, $activation->endsAt, null, LiftReason::Expired);
                $clock->modify('+1 hour');
            }

            for ($current = 0; $current < $inForce; ++$current) {
                $incidents->start(0 === $current ? Presets::SUSPEND_SIGNUPS : Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(2), Actor::of('ops-1'));
            }

            $this->checkpoints[$case] = new Checkpoint($incidents, new IncidentRestrictions($incidents, new NoEstablishedMembers()), new Member(), $clock);
        }
    }

    /**
     * @param array{case: string} $params
     *
     * @ParamProviders("cases")
     *
     * @Revs(500)
     */
    public function benchSignUpCheckpoint(array $params): void
    {
        $this->suspension = $this->checkpoints[$params['case']]->suspension(Restriction::SIGNUPS);
    }

    /**
     * @param array{case: string} $params
     *
     * @ParamProviders("cases")
     *
     * @Revs(500)
     */
    public function benchBanner(array $params): void
    {
        $this->suspensions = $this->checkpoints[$params['case']]->suspensions();
    }

    /**
     * @return Generator<string, array{case: string}>
     */
    public function cases(): Generator
    {
        yield 'quiet' => ['case' => 'quiet'];
        yield 'two incidents' => ['case' => 'two incidents'];
    }
}
