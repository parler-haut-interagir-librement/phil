<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;

final class MemoryGuestRequests implements GuestRequestStore
{
    /**
     * @var array<string, GuestRequest>
     */
    public array $requests = [];

    public int $purges = 0;

    #[Override]
    public function save(GuestRequest $request): void
    {
        $this->requests[$request->id] = $request;
    }

    #[Override]
    public function find(string $id): ?GuestRequest
    {
        return $this->requests[$id] ?? null;
    }

    #[Override]
    public function delete(string $id): void
    {
        unset($this->requests[$id]);
    }

    #[Override]
    public function retain(string $id, DateTimeImmutable $until): void
    {
        $request = $this->requests[$id] ?? null;

        if ($request instanceof GuestRequest) {
            $this->requests[$id] = new GuestRequest($id, $request->kind, $request->email, hash('sha256', 'retained'), $request->requestedAt, $until);
        }
    }

    #[Override]
    public function purgeExpired(DateTimeImmutable $now): int
    {
        ++$this->purges;
        $before = \count($this->requests);
        $this->requests = array_filter($this->requests, static fn (GuestRequest $request): bool => !$request->isExpired($now));

        return $before - \count($this->requests);
    }
}
