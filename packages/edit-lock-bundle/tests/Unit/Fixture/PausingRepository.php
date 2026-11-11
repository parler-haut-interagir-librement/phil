<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;

/**
 * A store that lets another request run in the middle of a claim: the first time it is asked for a lease, once the
 * claim has read the resource's leases and before it writes its own, it calls `$meanwhile`.
 */
final class PausingRepository implements LeaseRepository
{
    /**
     * @param Closure(): void $meanwhile
     */
    public function __construct(
        private readonly LeaseRepository $inner,
        private ?Closure $meanwhile,
    ) {
    }

    #[Override]
    public function exclusively(string $resource, Closure $claim): mixed
    {
        return $this->inner->exclusively($resource, $claim);
    }

    #[Override]
    public function insert(Lease $lease): bool
    {
        return $this->inner->insert($lease);
    }

    #[Override]
    public function replace(Lease $read, Lease $next): bool
    {
        return $this->inner->replace($read, $next);
    }

    #[Override]
    public function delete(LeaseKey $key, string $holderId): bool
    {
        return $this->inner->delete($key, $holderId);
    }

    #[Override]
    public function get(LeaseKey $key): ?Lease
    {
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $this->inner->get($key);
    }

    #[Override]
    public function ofResource(string $resource): array
    {
        return $this->inner->ofResource($resource);
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->inner->deleteExpired($now);
    }
}
