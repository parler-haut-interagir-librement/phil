<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Memory;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;

/**
 * The leases in memory, for the tests of an application and for a single long-running process; each request of a
 * PHP-FPM application would start from none. One process runs one claim at a time.
 */
final class InMemoryLeaseRepository implements LeaseRepository
{
    /** @var array<string, Lease> */
    private array $leases = [];

    #[Override]
    public function exclusively(string $resource, Closure $claim): mixed
    {
        return $claim();
    }

    #[Override]
    public function insert(Lease $lease): bool
    {
        $id = $lease->key()->toString();

        if (isset($this->leases[$id])) {
            return false;
        }

        $this->leases[$id] = $lease;

        return true;
    }

    #[Override]
    public function replace(Lease $read, Lease $next): bool
    {
        $id = $next->key()->toString();

        if (($this->leases[$id] ?? null)?->version !== $read->version) {
            return false;
        }

        $this->leases[$id] = $next;

        return true;
    }

    #[Override]
    public function delete(LeaseKey $key, string $holderId): bool
    {
        $id = $key->toString();

        if (($this->leases[$id] ?? null)?->holder()->id !== $holderId) {
            return false;
        }

        unset($this->leases[$id]);

        return true;
    }

    #[Override]
    public function get(LeaseKey $key): ?Lease
    {
        return $this->leases[$key->toString()] ?? null;
    }

    #[Override]
    public function ofResource(string $resource): array
    {
        $leases = array_filter($this->leases, static fn (Lease $lease): bool => $lease->key()->resource === $resource);
        usort($leases, static fn (Lease $one, Lease $other): int => ($one->key()->part ?? '') <=> ($other->key()->part ?? ''));

        return $leases;
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        $before = \count($this->leases);
        $this->leases = array_filter($this->leases, static fn (Lease $lease): bool => $lease->isActiveAt($now));

        return $before - \count($this->leases);
    }
}
