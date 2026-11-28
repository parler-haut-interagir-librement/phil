<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\WaitlistBundle\Domain\Contract\WaitlistRepository;
use Phil\WaitlistBundle\Domain\Model\Waitlist;

/**
 * The waiting lists in an array; the lock records the lists it was taken on, and whether the work ran inside it.
 */
final class InMemoryWaitlists implements WaitlistRepository
{
    /** @var array<string, Waitlist> */
    public array $waitlists = [];

    /** @var list<string> */
    public array $locked = [];

    public bool $inside = false;

    /** @var list<string> the lists saved, by identifier, and whether inside the lock */
    public array $saved = [];

    #[Override]
    public function get(string $id): ?Waitlist
    {
        return $this->waitlists[$id] ?? null;
    }

    #[Override]
    public function ofSubject(string $subjectType, string $subjectId): ?Waitlist
    {
        foreach ($this->waitlists as $waitlist) {
            if ($waitlist->subjectType() === $subjectType && $waitlist->subjectId() === $subjectId) {
                return $waitlist;
            }
        }

        return null;
    }

    #[Override]
    public function save(Waitlist $waitlist): void
    {
        $this->waitlists[$waitlist->id] = $waitlist;
        $this->saved[] = $waitlist->id.($this->inside ? ' locked' : ' unlocked');
    }

    #[Override]
    public function exclusively(Waitlist $waitlist, Closure $work): mixed
    {
        $this->locked[] = $waitlist->id;
        $this->inside = true;

        try {
            return $work();
        } finally {
            $this->inside = false;
        }
    }
}
