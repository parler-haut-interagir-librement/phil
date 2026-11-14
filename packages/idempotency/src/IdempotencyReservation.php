<?php

declare(strict_types=1);

namespace Phil\Idempotency;

/**
 * The hold of one run on a key: its scope, its key, and a random token that names this reservation alone.
 *
 * `IdempotencyStore::reserve()` returns it, and `complete()` and `fail()` take it back: a store writes the outcome
 * only while the key is still held under this token. A run that outlived its reservation, after which another run
 * reserved the key, can no longer overwrite the newer reservation; its store throws `ReservationLost` instead.
 */
final readonly class IdempotencyReservation
{
    /** The length of a token: 16 random bytes, in hexadecimal. */
    public const int TOKEN_LENGTH = 32;

    private function __construct(
        public IdempotencyScope $scope,
        public IdempotencyKey $key,
        public string $token,
    ) {
    }

    /**
     * A new reservation of the key, under a token no other reservation shares.
     */
    public static function create(IdempotencyScope $scope, IdempotencyKey $key): self
    {
        return new self($scope, $key, sodium_bin2hex(random_bytes(self::TOKEN_LENGTH / 2)));
    }
}
