<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalAbsenceRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalGuardianRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalRosterRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalSwapRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalTransaction;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle's services on a SQLite database in memory, its tables created, a clock the tests move — the Friday before
 * a Saturday of a convention, 2026-11-27 at 10:00 UTC —, a dispatcher that keeps the events, the birth dates the tests
 * give and the threshold of absences they ask for.
 */
final class Stage implements EventDispatcherInterface
{
    public readonly Connection $connection;

    public readonly MockClock $clock;

    public readonly DbalRosterRepository $rosterRepository;

    public readonly DbalSwapRepository $swapRepository;

    public readonly Rosters $rosters;

    public readonly SwapBoard $swaps;

    public readonly DbalGuardianRepository $guardianRepository;

    public readonly DbalAbsenceRepository $absenceRepository;

    public readonly KnownBirthDates $birthDates;

    public readonly Minors $minors;

    public readonly Absences $absences;

    /** @var list<object> */
    public array $events = [];

    /**
     * @param array<string, string>|null $birthDates the birth dates of the volunteers, or null for no source of them
     */
    public function __construct(int $restMinutes = 0, ?int $absenceThreshold = null, ?array $birthDates = null, string $timeZone = 'UTC')
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (ShiftsTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->clock = new MockClock('2026-11-27 10:00:00 UTC');
        $this->rosterRepository = new DbalRosterRepository($this->connection, $this->clock);
        $this->swapRepository = new DbalSwapRepository($this->connection);
        $this->guardianRepository = new DbalGuardianRepository($this->connection);
        $this->absenceRepository = new DbalAbsenceRepository($this->connection);
        $this->birthDates = new KnownBirthDates($birthDates ?? []);
        $this->minors = new Minors($this->guardianRepository, $this->clock, null === $birthDates ? null : $this->birthDates, 18, $timeZone);
        $this->absences = new Absences($this->absenceRepository, $this->rosterRepository, $this->clock, $absenceThreshold);
        $this->rosters = new Rosters($this->rosterRepository, $this->clock, new Conflicts($restMinutes), new FairAllocator(), $this, $this->minors, $this->absences);
        $this->swaps = new SwapBoard($this->swapRepository, $this->rosterRepository, $this->rosters, new DbalTransaction($this->connection), $this->clock, new Conflicts($restMinutes), $this);
    }

    /**
     * Forgets the events dispatched so far.
     */
    public function forget(): void
    {
        $this->events = [];
    }

    /**
     * @return list<object> the events dispatched since the last forget()
     *
     * @phpstan-impure
     */
    public function events(): array
    {
        return $this->events;
    }

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-28T'.$time.'Z');
    }

    public static function desk(string $id = 'desk-am', string $from = '09:00:00', string $to = '12:00:00', int $capacity = 2, int $reserve = 1, AssignmentMode $mode = AssignmentMode::SelfSignUp, ?string $role = null, bool $approval = false): Shift
    {
        return new Shift($id, 'Info desk', ShiftKind::Fixed, self::at($from), self::at($to), null, $capacity, $mode, $reserve, $role, false, 100, $approval, 'Hall A');
    }

    public static function cloakroom(int $capacity = 2): Shift
    {
        return new Shift('cloakroom', 'Cloakroom', ShiftKind::Duration, self::at('08:00:00'), self::at('20:00:00'), 120, $capacity, AssignmentMode::Interest, 0, null, true, 150);
    }
}
