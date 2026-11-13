<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use DateTimeImmutable;
use Override;
use Phil\IncidentMode\Contract\ActivationStore;

/**
 * The activations in an array, for tests and for a single process: nothing survives the process, and `reset()`
 * empties it between two requests of a worker.
 */
final class InMemoryActivationStore implements ActivationStore
{
    /** @var array<string, Activation> */
    private array $activations = [];

    #[Override]
    public function add(Activation $activation): void
    {
        $this->activations[$activation->id] = $activation;
    }

    #[Override]
    public function find(string $id): ?Activation
    {
        return $this->activations[$id] ?? null;
    }

    #[Override]
    public function unliftedEndingAfter(DateTimeImmutable $now): array
    {
        $found = array_filter($this->activations, static fn (Activation $activation): bool => !$activation->liftedAt instanceof DateTimeImmutable && $activation->endsAt > $now);
        usort($found, static fn (Activation $a, Activation $b): int => $a->startedAt <=> $b->startedAt);

        return $found;
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->activations, static fn (Activation $activation): bool => $activation->isDueAt($now));
        usort($due, static fn (Activation $a, Activation $b): int => $a->endsAt <=> $b->endsAt);

        return array_map(static fn (Activation $activation): string => $activation->id, \array_slice($due, 0, $limit));
    }

    #[Override]
    public function lift(string $id, DateTimeImmutable $at, ?Actor $by, LiftReason $reason): bool
    {
        $activation = $this->activations[$id] ?? null;

        if (!$activation instanceof Activation || $activation->liftedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->activations[$id] = $activation->liftedOn($at, $by, $reason);

        return true;
    }

    #[Override]
    public function extend(string $id, DateTimeImmutable $endsAt): bool
    {
        $activation = $this->activations[$id] ?? null;

        if (!$activation instanceof Activation || $activation->liftedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->activations[$id] = $activation->endingAt($endsAt);

        return true;
    }

    #[Override]
    public function history(int $limit, int $offset = 0): array
    {
        $all = array_values($this->activations);
        usort($all, static fn (Activation $a, Activation $b): int => $b->startedAt <=> $a->startedAt);

        return \array_slice($all, $offset, $limit);
    }

    #[Override]
    public function ofActor(Actor $actor): array
    {
        return array_values(array_filter($this->history(\PHP_INT_MAX), static fn (Activation $activation): bool => $activation->startedBy?->id === $actor->id || $activation->liftedBy?->id === $actor->id));
    }

    #[Override]
    public function forgetActor(Actor $actor): int
    {
        $changed = 0;

        foreach ($this->activations as $id => $activation) {
            $startedBy = $activation->startedBy?->id === $actor->id ? null : $activation->startedBy;
            $liftedBy = $activation->liftedBy?->id === $actor->id ? null : $activation->liftedBy;

            if ($startedBy !== $activation->startedBy || $liftedBy !== $activation->liftedBy) {
                $this->activations[$id] = new Activation($activation->id, $activation->preset, $activation->restrictions, $activation->startedAt, $activation->endsAt, $startedBy, $activation->reason, $activation->liftedAt, $liftedBy, $activation->liftReason);
                ++$changed;
            }
        }

        return $changed;
    }

    public function reset(): void
    {
        $this->activations = [];
    }
}
