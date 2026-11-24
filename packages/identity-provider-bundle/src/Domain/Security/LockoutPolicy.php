<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Security;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;

/**
 * Progressive lockout: after `threshold` failed sign-ins in a row, each further failure locks the account for twice as
 * long as the one before, from `base` seconds up to `max`. A success resets the count. The lock refuses the password
 * only; a passkey, which a guessing attacker cannot replay, still signs in.
 *
 * A policy that is not enabled never locks: the failures are still counted and recorded, and guessing is left to the
 * limiter of the firewall's `login_throttling`, per address and per identifier, which no one can turn against the owner
 * of an account by failing on purpose.
 */
final readonly class LockoutPolicy
{
    public function __construct(
        public int $threshold = 5,
        public int $base = 60,
        public int $max = 3600,
        public bool $enabled = true,
    ) {
        if ($threshold < 1 || $base < 1 || $max < $base) {
            throw InvalidValue::because('The lockout needs a threshold and a base of at least 1, and a maximum not below the base.');
        }
    }

    /**
     * Until when an account with this many failures in a row is locked; null when it is not.
     */
    public function lockedUntil(int $failures, DateTimeImmutable $lastFailure): ?DateTimeImmutable
    {
        if (!$this->enabled || $failures < $this->threshold) {
            return null;
        }

        $exponent = min($failures - $this->threshold, 30);
        $seconds = min($this->base * (1 << $exponent), $this->max);

        return $lastFailure->modify(\sprintf('+%d seconds', $seconds));
    }
}
