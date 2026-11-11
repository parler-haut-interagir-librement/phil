<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanId;

/**
 * An in-memory ledger whose appends can be preceded by what another device does, and which counts its calls.
 */
final class ScriptedLedger implements CheckInLedger
{
    public int $appends = 0;

    /** @var list<list<string>> */
    public array $attendanceCalls = [];

    /**
     * @param list<Closure(InMemoryCheckInLedger): void> $before what happens before each append, in turn
     */
    public function __construct(
        public InMemoryCheckInLedger $inner = new InMemoryCheckInLedger(),
        private array $before = [],
        private readonly bool $refuseAll = false,
    ) {
    }

    #[Override]
    public function attendance(string $credentialId): Attendance
    {
        return $this->inner->attendance($credentialId);
    }

    #[Override]
    public function attendances(array $credentialIds): array
    {
        $this->attendanceCalls[] = $credentialIds;

        return $this->inner->attendances($credentialIds);
    }

    #[Override]
    public function find(ScanId $id): ?CheckIn
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function append(CheckIn $checkIn): bool
    {
        ++$this->appends;
        $before = array_shift($this->before);

        if (null !== $before) {
            $before($this->inner);
        }

        return !$this->refuseAll && $this->inner->append($checkIn);
    }
}
