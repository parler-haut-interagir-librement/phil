<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;

/**
 * A store where another request wins the next writes: each insertion or replacement it is asked for fails while
 * `$losses` lasts, and is counted; each claim gives up waiting for another one while `$timeouts` lasts, and is counted.
 */
final class RacingRepository implements LeaseRepository
{
    public int $writes = 0;

    public int $claims = 0;

    public function __construct(
        public int $losses,
        public InMemoryLeaseRepository $inner = new InMemoryLeaseRepository(),
        public int $timeouts = 0,
    ) {
    }

    #[Override]
    public function exclusively(string $resource, Closure $claim): mixed
    {
        ++$this->claims;

        return $this->timeouts-- > 0 ? null : $this->inner->exclusively($resource, $claim);
    }

    #[Override]
    public function insert(Lease $lease): bool
    {
        return $this->lose() && $this->inner->insert($lease);
    }

    #[Override]
    public function replace(Lease $read, Lease $next): bool
    {
        return $this->lose() && $this->inner->replace($read, $next);
    }

    #[Override]
    public function delete(LeaseKey $key, string $holderId): bool
    {
        return $this->inner->delete($key, $holderId);
    }

    #[Override]
    public function get(LeaseKey $key): ?Lease
    {
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

    private function lose(): bool
    {
        ++$this->writes;

        return $this->losses-- <= 0;
    }
}
