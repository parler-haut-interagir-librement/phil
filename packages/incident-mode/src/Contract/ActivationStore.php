<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Contract;

use DateTimeImmutable;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\LiftReason;

/**
 * Where the activations are kept: those in force, and the journal of every one.
 *
 * `lift()` and `extend()` change a row only while it is not lifted, and say whether they did: two processes that lift
 * the same activation at once see one of them succeed, so its event is sent once.
 */
interface ActivationStore
{
    public function add(Activation $activation): void;

    public function find(string $id): ?Activation;

    /**
     * The activations not lifted whose end is after the moment, the earliest start first.
     *
     * @return list<Activation>
     */
    public function unliftedEndingAfter(DateTimeImmutable $now): array;

    /**
     * The identifiers of the activations not lifted whose end is at or before the moment, the earliest end first.
     *
     * @param positive-int $limit
     *
     * @return list<string>
     */
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array;

    /**
     * Records the lifting of an activation that is not lifted yet.
     *
     * @return bool whether this call lifted it
     */
    public function lift(string $id, DateTimeImmutable $at, ?Actor $by, LiftReason $reason): bool;

    /**
     * Moves the end of an activation that is not lifted yet.
     *
     * @return bool whether this call moved it
     */
    public function extend(string $id, DateTimeImmutable $endsAt): bool;

    /**
     * The journal: every activation, the latest start first.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<Activation>
     */
    public function history(int $limit, int $offset = 0): array;

    /**
     * The activations the actor started or lifted, the latest start first.
     *
     * @return list<Activation>
     */
    public function ofActor(Actor $actor): array;

    /**
     * Removes the actor from the activations they started or lifted, which stay in the journal without them.
     *
     * @return int how many activations were changed
     */
    public function forgetActor(Actor $actor): int;
}
