<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use DateTimeImmutable;
use Phil\EditLockBundle\Application\Exception\EditRefused;
use Phil\EditLockBundle\Application\Exception\LeaseHeld;
use Phil\EditLockBundle\Application\Exception\LeaseLost;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Psr\Clock\ClockInterface;

/**
 * The leases of an application: a holder acquires a resource or one of its parts, keeps it by heartbeats, and
 * releases it; a lease nobody renews expires and passes to the next holder who asks; one the policy allows may take
 * it over. Each claim reads the resource's leases and writes its own while no other claim on the resource runs, so
 * that a lease on the whole and one on a part never go to two holders. Every change is told to the other writers
 * through the broadcaster.
 */
final readonly class EditLocks
{
    public const int SECONDS = 30;

    /** How many times a write that lost a race is tried again before giving up. */
    public const int ATTEMPTS = 3;

    private EditPolicy $policy;

    private Broadcaster $broadcaster;

    /**
     * @throws InvalidLease when a lease would last less than a second
     */
    public function __construct(
        private LeaseRepository $store,
        private ClockInterface $clock,
        private int $seconds = self::SECONDS,
        ?EditPolicy $policy = null,
        ?Broadcaster $broadcaster = null,
    ) {
        if ($seconds < 1) {
            throw InvalidLease::because('A lease lasts at least one second.');
        }

        $this->policy = $policy ?? new DefaultEditPolicy();
        $this->broadcaster = $broadcaster ?? new NullBroadcaster();
    }

    public function seconds(): int
    {
        return $this->seconds;
    }

    /**
     * Gives the holder the key for the lease's duration, renewing it when they hold it already.
     *
     * @throws EditRefused when the policy does not let the holder edit the resource
     * @throws LeaseHeld   when another holder holds the key, or what overlaps it
     */
    public function acquire(LeaseKey $key, Holder $holder): Lease
    {
        return $this->take($key, $holder, false);
    }

    /**
     * Gives the holder the key, removing the leases of other holders on it or on what overlaps it.
     *
     * @throws EditRefused when the policy does not let the holder edit the resource or take one of those leases over
     * @throws LeaseHeld   when requests kept racing for the key
     */
    public function takeOver(LeaseKey $key, Holder $holder): Lease
    {
        return $this->take($key, $holder, true);
    }

    /**
     * Keeps the holder's lease for another duration from now. The policy is asked again: a holder no longer allowed
     * to edit the resource loses the lease.
     *
     * @throws EditRefused when the policy no longer lets the holder edit the resource; the lease is released
     * @throws LeaseLost   when the holder no longer holds the key
     */
    public function heartbeat(LeaseKey $key, Holder $holder): Lease
    {
        if (!$this->policy->mayEdit($holder, $key->resource)) {
            $this->release($key, $holder);

            throw EditRefused::edit($key);
        }

        $now = $this->clock->now();
        $next = $this->store->exclusively($key->resource, function () use ($key, $holder, $now): ?Lease {
            $read = $this->store->get($key);

            if (!$read instanceof Lease || !$read->isHeldBy($holder) || [] !== $this->others($key, $holder, $now)) {
                throw LeaseLost::of($key);
            }

            $next = $read->renewed($holder, $now, $this->seconds);

            return $this->store->replace($read, $next) ? $next : null;
        });

        if (!$next instanceof Lease) {
            throw LeaseLost::of($key);
        }

        $this->tell($key->resource, $now);

        return $next;
    }

    /**
     * Gives up the holder's lease on a key.
     *
     * @return bool whether they held it
     */
    public function release(LeaseKey $key, Holder $holder): bool
    {
        if (!$this->store->delete($key, $holder->id)) {
            return false;
        }

        $this->tell($key->resource, $this->clock->now());

        return true;
    }

    /**
     * Gives up every lease of the holder on a resource and its parts, when they leave its page.
     *
     * @return int how many they held
     */
    public function leave(string $resource, Holder $holder): int
    {
        $released = 0;

        foreach ($this->store->ofResource($resource) as $lease) {
            if ($lease->isHeldBy($holder) && $this->store->delete($lease->key(), $holder->id)) {
                ++$released;
            }
        }

        if ($released > 0) {
            $this->tell($resource, $this->clock->now());
        }

        return $released;
    }

    /**
     * Whether the holder may write a key now: they hold it, or the whole resource, and the lease has not expired.
     */
    public function holds(LeaseKey $key, Holder $holder): bool
    {
        $now = $this->clock->now();

        return array_any($this->store->ofResource($key->resource), static fn (Lease $lease): bool => $lease->isHeldBy($holder) && $lease->isActiveAt($now) && $lease->key()->covers($key));
    }

    /**
     * Whether the policy lets the holder edit a resource.
     */
    public function mayEdit(Holder $holder, string $resource): bool
    {
        return $this->policy->mayEdit($holder, $resource);
    }

    /**
     * The leases active on a resource and its parts: who edits what, and until when.
     *
     * @return list<Lease>
     */
    public function presence(string $resource): array
    {
        return array_values($this->active($resource, $this->clock->now()));
    }

    /**
     * Removes the expired leases, from a scheduled task for instance; an expired lease is replaced anyway when
     * someone asks for its key.
     *
     * @return int how many were removed
     */
    public function purge(): int
    {
        return $this->store->deleteExpired($this->clock->now());
    }

    private function take(LeaseKey $key, Holder $holder, bool $takeover): Lease
    {
        if (!$this->policy->mayEdit($holder, $key->resource)) {
            throw EditRefused::edit($key);
        }

        for ($attempt = 1; $attempt <= self::ATTEMPTS; ++$attempt) {
            $now = $this->clock->now();
            $next = $this->store->exclusively($key->resource, fn (): ?Lease => $this->claim($key, $holder, $takeover, $now));

            if ($next instanceof Lease) {
                $this->tell($key->resource, $now);

                return $next;
            }
        }

        throw LeaseHeld::contended($key);
    }

    /**
     * One attempt at giving the holder the key, while no other claim on the resource runs.
     *
     * @return Lease|null the lease kept, or null when another request wrote the key in between
     *
     * @throws EditRefused when the policy does not let the holder take over a lease that overlaps the key
     * @throws LeaseHeld   when another holder holds the key, or what overlaps it, and this is no takeover
     */
    private function claim(LeaseKey $key, Holder $holder, bool $takeover, DateTimeImmutable $now): ?Lease
    {
        $others = $this->others($key, $holder, $now);

        foreach ($others as $lease) {
            if (!$takeover) {
                throw LeaseHeld::by($lease);
            }

            if (!$this->policy->mayTakeOver($holder, $lease)) {
                throw EditRefused::takeover($lease->key());
            }
        }

        foreach ($others as $lease) {
            if (!$lease->key()->equals($key)) {
                $this->store->delete($lease->key(), $lease->holder()->id);
            }
        }

        $read = $this->store->get($key);

        if (!$read instanceof Lease) {
            $next = Lease::grant($key, $holder, $now, $this->seconds);

            return $this->store->insert($next) ? $next : null;
        }

        $next = $read->isHeldBy($holder) ? $read->renewed($holder, $now, $this->seconds) : $read->passedTo($holder, $now, $this->seconds);

        return $this->store->replace($read, $next) ? $next : null;
    }

    /**
     * The active leases of other holders that a lease on the key would overlap.
     *
     * @return array<int, Lease>
     */
    private function others(LeaseKey $key, Holder $holder, DateTimeImmutable $now): array
    {
        return array_filter(
            $this->active($key->resource, $now),
            static fn (Lease $lease): bool => !$lease->isHeldBy($holder) && $lease->key()->overlaps($key),
        );
    }

    /**
     * @return array<int, Lease>
     */
    private function active(string $resource, DateTimeImmutable $now): array
    {
        return array_filter($this->store->ofResource($resource), static fn (Lease $lease): bool => $lease->isActiveAt($now));
    }

    private function tell(string $resource, DateTimeImmutable $now): void
    {
        $this->broadcaster->presence($resource, array_values($this->active($resource, $now)), $now);
    }
}
