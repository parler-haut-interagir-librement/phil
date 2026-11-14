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
     * @return int how many expired requests were removed
     */
    public function purgeExpired(DateTimeImmutable $now): int;
}
