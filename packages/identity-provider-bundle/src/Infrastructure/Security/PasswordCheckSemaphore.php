<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * A cap on the password hashes computed at once. Argon2id takes tens of mebibytes for each try: a burst of failed
 * sign-ins spread over many accounts passes under the limiters by address and by account, yet a server that hashed
 * them all at once would run out of memory. Each hash takes one of `$slots` locks of the Lock component, waits up to
 * `$wait` seconds for one to free, and is refused with `password.busy` past that wait — a refusal that never counts
 * as a wrong password.
 *
 * The locks belong to the server that hashes, so a store local to it — the default, `FlockStore` — caps each server;
 * a lock dies with the process that held it.
 */
final readonly class PasswordCheckSemaphore
{
    public const string MESSAGE_KEY = 'password.busy';

    private const string KEY = 'phil_identity_provider.password_check.';

    /** The longest pause between two rounds over the slots, in microseconds. */
    private const int PAUSE = 5_000;

    /**
     * @param positive-int $slots how many hashes run at once
     * @param float        $wait  how long a hash waits for a slot, in seconds
     */
    public function __construct(
        private LockFactory $locks,
        private int $slots,
        private float $wait,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $hash
     *
     * @return T
     *
     * @throws CustomUserMessageAuthenticationException with `password.busy` when no slot frees within the wait
     */
    public function run(callable $hash): mixed
    {
        $deadline = self::now() + (int) round($this->wait * 1_000_000);
        $first = random_int(0, $this->slots - 1);

        do {
            for ($i = 0; $i < $this->slots; ++$i) {
                $lock = $this->locks->createLock(self::KEY.(($first + $i) % $this->slots));

                if (!$lock->acquire()) {
                    continue;
                }

                try {
                    return $hash();
                } finally {
                    $lock->release();
                }
            }

            $remaining = $deadline - self::now();

            if ($remaining > 0) {
                usleep(min(self::PAUSE, $remaining));
            }
        } while ($remaining > 0);

        throw new CustomUserMessageAuthenticationException(self::MESSAGE_KEY);
    }

    /**
     * The monotonic clock, in microseconds.
     */
    private static function now(): int
    {
        [$seconds, $nanoseconds] = hrtime();

        return $seconds * 1_000_000 + intdiv($nanoseconds, 1000);
    }
}
