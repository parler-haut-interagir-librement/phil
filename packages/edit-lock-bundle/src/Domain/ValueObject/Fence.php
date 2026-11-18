<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\ValueObject;

use Phil\EditLockBundle\Domain\Exception\InvalidLease;

/**
 * The fencing token of a write made under a lease: the key written, its holder, and the number of the claim on the
 * resource the write ran in. Every claim on a resource, a write included, gets a greater number than the claims before
 * it, so a store that keeps the number of the last write it accepted for a key, and accepts a write only when its
 * number is greater, by one conditional `UPDATE` whose row count it checks, refuses a write that comes after a newer
 * one, however late it arrives.
 */
final readonly class Fence
{
    private function __construct(
        public LeaseKey $key,
        public Holder $holder,
        public int $number,
    ) {
    }

    /**
     * @throws InvalidLease when the number is not positive
     */
    public static function of(LeaseKey $key, Holder $holder, int $number): self
    {
        if ($number < 1) {
            throw InvalidLease::because('The number of a fence is a positive number.');
        }

        return new self($key, $holder, $number);
    }

    /**
     * Whether this write comes after one fenced by `$other`: a store keeps the newer and refuses the older.
     */
    public function isNewerThan(self $other): bool
    {
        return $this->number > $other->number;
    }
}
