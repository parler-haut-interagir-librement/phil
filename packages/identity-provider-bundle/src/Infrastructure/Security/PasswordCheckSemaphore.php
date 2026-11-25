<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
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
     * @param positive-int   $slots how many hashes run at once
     * @param float          $wait  how long a hash waits for a slot, in seconds
     * @param ClockInterface $clock the clock of the wait, which is monotonic unless a test gives another
     */
    public function __construct(
        private LockFactory $locks,
        private int $slots,
        private float $wait,
        private ClockInterface $clock = new MonotonicClock(),
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
        $deadline = $this->microseconds() + round($this->wait * 1_000_000);
        // Where the round over the slots starts: the load spreads over the slots; the remainder of a negative draw is made positive, which keeps the draw even.
        $first = abs(random_int(\PHP_INT_MIN, \PHP_INT_MAX) % $this->slots);

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

            $remaining = $deadline - $this->microseconds();

            if ($remaining > 0) {
                $this->clock->sleep(min(self::PAUSE, $remaining) / 1_000_000);
            }
        } while ($remaining > 0);

        throw new CustomUserMessageAuthenticationException(self::MESSAGE_KEY);
    }

    /**
     * The clock, in microseconds.
     */
    private function microseconds(): int
    {
        return (int) $this->clock->now()->format('Uu');
    }
}
