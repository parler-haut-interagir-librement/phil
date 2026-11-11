<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * How hard a password is to guess, from 0 to 4, by the order of magnitude of its guesses.
 */
enum Score: int
{
    /** Fewer than a thousand guesses: found at once, even online with throttling. */
    case VeryWeak = 0;

    /** Fewer than a million guesses: found online without throttling. */
    case Weak = 1;

    /** Fewer than a hundred million guesses: resists online attacks, not an offline one. */
    case Fair = 2;

    /** Fewer than ten billion guesses: resists an offline attack on a slow hash for a while. */
    case Strong = 3;

    /** Ten billion guesses or more. */
    case VeryStrong = 4;

    public static function fromGuesses(float $guesses): self
    {
        return match (true) {
            $guesses < 1e3 => self::VeryWeak,
            $guesses < 1e6 => self::Weak,
            $guesses < 1e8 => self::Fair,
            $guesses < 1e10 => self::Strong,
            default => self::VeryStrong,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->value >= $minimum->value;
    }
}
