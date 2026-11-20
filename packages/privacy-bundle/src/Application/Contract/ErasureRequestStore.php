<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Closure;
use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;

/**
 * Keeps the erasure requests and one step per module, unique for the pair of a request and a module.
 *
 * A step changes state only through these methods, each of which changes it only from the states it names, in one
 * statement: two workers never run the same step while its lease runs, and a finished step is never run again.
 */
interface ErasureRequestStore
{
    /**
     * Adds a request and its pending steps, and runs `$alsoInTransaction` in the same transaction, such as the outbox
     * records of the modules: all of it commits, or none of it does.
     *
     * @param list<ErasureStep> $steps
     * @param Closure(): void   $alsoInTransaction
     */
    public function open(ErasureRequest $request, array $steps, Closure $alsoInTransaction): void;

    public function find(string $id): ?ErasureRequest;

    /**
     * @return list<ErasureStep> sorted by module
     */
    public function steps(string $id): array;

    public function step(string $id, string $module): ?ErasureStep;

    /**
     * Leases a step to a worker until `$leaseUntil`: one that is pending or needs attention, or one running under a
     * lease that ran out at `$now`. A step running without a lease waits for its acknowledgement and is never leased.
     *
     * @param non-empty-string $lease a token unique to this attempt, which the marks below repeat
     *
     * @return bool false when the step is not there to lease
     */
    public function lease(string $id, string $module, string $lease, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): bool;

    /**
     * Completes a step leased under `$lease`, with what the module did.
     *
     * @param array<string, ErasureEffect> $effects by store
     *
     * @return bool false when the step is no longer leased under that token
     */
    public function complete(string $id, string $module, string $lease, array $effects, DateTimeImmutable $now): bool;

    /**
     * Ends the lease of a step whose module sent the erasure to an external service: the step stays running, without a
     * lease, until its acknowledgement.
     *
     * @return bool false when the step is no longer leased under that token
     */
    public function await(string $id, string $module, string $lease, DateTimeImmutable $now): bool;

    /**
     * Completes a step that waits for its acknowledgement, found by its idempotency key.
     *
     * @param array<string, ErasureEffect> $effects by store
     *
     * @return bool false when no step waits under that key
     */
    public function acknowledge(string $idempotencyKey, array $effects, DateTimeImmutable $now): bool;

    /**
     * Gives a step leased under `$lease` back after a failed attempt: pending, one more attempt counted.
     *
     * @return bool false when the step is no longer leased under that token
     */
    public function release(string $id, string $module, string $lease, DateTimeImmutable $now): bool;

    /**
     * Marks a step that is pending or running as needing attention, for a reason.
     *
     * @return bool false when the step is completed, already needs attention, or is not there
     */
    public function flag(string $id, string $module, AttentionReason $reason, DateTimeImmutable $now): bool;

    /**
     * Puts back to pending the steps of a request that need attention or wait for an acknowledgement.
     *
     * @return list<string> the modules put back, sorted
     */
    public function reopen(string $id, DateTimeImmutable $now): array;

    /**
     * Sets the state of a request, closed at `$closedAt` when the state closes it. A closed request is left as it is.
     *
     * @return bool false when the request is closed or not there
     */
    public function settle(string $id, RequestState $state, ?DateTimeImmutable $closedAt): bool;
}
