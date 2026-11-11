<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\Repository;

use Closure;
use DateTimeImmutable;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;

/**
 * Where leases are kept. Every write is a compare-and-set, so that two requests racing for the same key never both
 * win: an insertion fails when the key is taken, a replacement when the version kept is no longer the one read. Claims
 * on one resource run one at a time, so that two requests racing for the whole resource and one of its parts never
 * both win either.
 */
interface LeaseRepository
{
    /**
     * Runs a claim on a resource while no other claim on it runs: the leases it reads stay as they are until its
     * writes are kept. An exception thrown by the claim is thrown again; a store with transactions undoes the claim's
     * writes first.
     *
     * @template T
     *
     * @param Closure(): T $claim
     *
     * @return T|null what the claim returned, or null when the store gave up waiting for another claim, its writes
     *                undone
     */
    public function exclusively(string $resource, Closure $claim): mixed;

    /**
     * Keeps a new lease, unless one is already kept on its key.
     *
     * @return bool whether it was kept
     */
    public function insert(Lease $lease): bool;

    /**
     * Replaces the lease kept on the key of `$next` by it, if the version kept is still that of `$read`.
     *
     * @return bool whether it was replaced
     */
    public function replace(Lease $read, Lease $next): bool;

    /**
     * Removes the lease kept on a key, if the holder of this identifier holds it.
     *
     * @return bool whether one was removed
     */
    public function delete(LeaseKey $key, string $holderId): bool;

    public function get(LeaseKey $key): ?Lease;

    /**
     * The leases kept on a resource and its parts, expired ones included, the whole resource first, then by part.
     *
     * @return list<Lease>
     */
    public function ofResource(string $resource): array;

    /**
     * Removes the leases expired at a moment.
     *
     * @return int how many were removed
     */
    public function deleteExpired(DateTimeImmutable $now): int;
}
