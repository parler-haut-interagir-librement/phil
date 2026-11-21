<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;

/**
 * Keeps the requests made without an account while their link is valid. An adapter keeps the address out of reach of a
 * copy of its store: the bundle's table seals it.
 */
interface GuestRequestStore
{
    public function save(GuestRequest $request): void;

    public function find(string $id): ?GuestRequest;

    public function delete(string $id): void;

    /**
     * Keeps a request whose erasure runs as a workflow until `$until`, past the expiry of its link, so that each module
     * can still find the person: its link no longer opens it, and it is removed once the erasure is closed, or at
     * `$until` at the latest.
     */
    public function retain(string $id, DateTimeImmutable $until): void;

    /**
     * @return int how many expired requests were removed
     */
    public function purgeExpired(DateTimeImmutable $now): int;
}
