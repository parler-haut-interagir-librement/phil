<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;

/**
 * The registrations in an array, in the order they were saved first; the lock records the sessions it was taken on,
 * and whether the work ran inside it.
 */
final class InMemoryRegistrations implements RegistrationRepository
{
    /** @var array<string, Registration> */
    public array $registrations = [];

    /** @var list<string> */
    public array $locked = [];

    public bool $inside = false;

    /** @var list<string> the registrations saved, by identifier, and whether inside the lock */
    public array $saved = [];

    #[Override]
    public function get(string $id): ?Registration
    {
        return $this->registrations[$id] ?? null;
    }

    #[Override]
    public function save(Registration $registration): void
    {
        $this->registrations[$registration->id] = $registration;
        $this->saved[] = $registration->id.($this->inside ? ' locked' : ' unlocked');
    }

    #[Override]
    public function live(WorkshopSession $session, string $participant): ?Registration
    {
        foreach ($this->registrations as $registration) {
            if ($registration->session() === $session && $registration->participant() === $participant && $registration->status()->isLive()) {
                return $registration;
            }
        }

        return null;
    }

    #[Override]
    public function seats(WorkshopSession $session, RegistrationStatus $status): int
    {
        return array_sum(array_map(static fn (Registration $registration): int => $registration->seats(), $this->ofSession($session, \PHP_INT_MAX, $status)));
    }

    #[Override]
    public function ofSession(WorkshopSession $session, int $limit, ?RegistrationStatus $status = null): array
    {
        $found = array_values(array_filter($this->registrations, static fn (Registration $registration): bool => $registration->session() === $session && (!$status instanceof RegistrationStatus || $registration->status() === $status)));

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function exclusively(WorkshopSession $session, Closure $work): mixed
    {
        $this->locked[] = $session->id;
        $this->inside = true;

        try {
            return $work();
        } finally {
            $this->inside = false;
        }
    }
}
