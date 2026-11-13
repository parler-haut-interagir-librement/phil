<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use DateTimeImmutable;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\LiftReason;

/**
 * A store that answers the activations ahead with the latest start first.
 */
final readonly class LatestFirstStore implements ActivationStore
{
    private InMemoryActivationStore $inner;

    public function __construct()
    {
        $this->inner = new InMemoryActivationStore();
    }

    #[Override]
    public function add(Activation $activation): void
    {
        $this->inner->add($activation);
    }

    #[Override]
    public function find(string $id): ?Activation
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function unliftedEndingAfter(DateTimeImmutable $now): array
    {
        return array_reverse($this->inner->unliftedEndingAfter($now));
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        return $this->inner->dueIdentifiers($now, $limit);
    }

    #[Override]
    public function lift(string $id, DateTimeImmutable $at, ?Actor $by, LiftReason $reason): bool
    {
        return $this->inner->lift($id, $at, $by, $reason);
    }

    #[Override]
    public function extend(string $id, DateTimeImmutable $endsAt): bool
    {
        return $this->inner->extend($id, $endsAt);
    }

    #[Override]
    public function history(int $limit, int $offset = 0): array
    {
        return $this->inner->history($limit, $offset);
    }

    #[Override]
    public function ofActor(Actor $actor): array
    {
        return $this->inner->ofActor($actor);
    }

    #[Override]
    public function forgetActor(Actor $actor): int
    {
        return $this->inner->forgetActor($actor);
    }
}
