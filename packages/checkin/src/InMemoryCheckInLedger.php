<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Override;
use Phil\Checkin\Contract\CheckInLedger;

/**
 * A ledger in the memory of one process, for tests and demonstrations: what it records is lost with the process and
 * shared with no other.
 */
final class InMemoryCheckInLedger implements CheckInLedger
{
    /** @var array<string, CheckIn> */
    private array $checkIns = [];

    /** @var array<string, Attendance> */
    private array $attendances = [];

    #[Override]
    public function attendance(string $credentialId): Attendance
    {
        return $this->attendances[$credentialId] ?? Attendance::none();
    }

    #[Override]
    public function attendances(array $credentialIds): array
    {
        $attendances = [];

        foreach ($credentialIds as $credentialId) {
            $attendances[$credentialId] = $this->attendance($credentialId);
        }

        return $attendances;
    }

    /**
     * @phpstan-pure
     */
    #[Override]
    public function find(ScanId $id): ?CheckIn
    {
        return $this->checkIns[$id->value] ?? null;
    }

    #[Override]
    public function append(CheckIn $checkIn): bool
    {
        $attendance = $this->attendance($checkIn->credentialId);

        if (isset($this->checkIns[$checkIn->id->value]) || (null !== $checkIn->sequence && $checkIn->sequence !== $attendance->version + 1)) {
            return false;
        }

        $this->checkIns[$checkIn->id->value] = $checkIn;
        $this->attendances[$checkIn->credentialId] = $attendance->after($checkIn);

        return true;
    }

    /**
     * Every check-in recorded, in the order recorded.
     *
     * @return list<CheckIn>
     */
    public function all(): array
    {
        return array_values($this->checkIns);
    }
}
