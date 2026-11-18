<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\LiftReason;

/**
 * A store that counts the questions about the incidents in force, the one every check asks, before passing each on.
 */
final class CountingStore implements ActivationStore
{
    public int $reads = 0;

    public function __construct(
        private readonly ActivationStore $store,
    ) {
    }

    #[Override]
    public function add(Activation $activation): void
    {
        $this->store->add($activation);
    }

    #[Override]
    public function find(string $id): ?Activation
    {
        return $this->store->find($id);
    }

    #[Override]
    public function unliftedEndingAfter(DateTimeImmutable $now): array
    {
        ++$this->reads;

        return $this->store->unliftedEndingAfter($now);
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        return $this->store->dueIdentifiers($now, $limit);
    }

    #[Override]
    public function lift(string $id, DateTimeImmutable $at, ?Actor $by, LiftReason $reason): bool
    {
        return $this->store->lift($id, $at, $by, $reason);
    }

    #[Override]
    public function extend(string $id, DateTimeImmutable $endsAt): bool
    {
        return $this->store->extend($id, $endsAt);
    }

    #[Override]
    public function history(int $limit, int $offset = 0): array
    {
        return $this->store->history($limit, $offset);
    }

    #[Override]
    public function ofActor(Actor $actor): array
    {
        return $this->store->ofActor($actor);
    }

    #[Override]
    public function forgetActor(Actor $actor): int
    {
        return $this->store->forgetActor($actor);
    }
}
