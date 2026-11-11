<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * The seconds an attacker needs to try the guesses of a password, in four settings: online with throttling (100
 * guesses an hour), online without (10 a second), offline against a slow hash such as Argon2id or bcrypt (10 000 a
 * second) and offline against a fast hash (ten billion a second). Orders of magnitude, not promises.
 */
final readonly class CrackTimes
{
    public const float ONLINE_THROTTLED_PER_SECOND = 100 / 3600;

    public const float ONLINE_UNTHROTTLED_PER_SECOND = 10.0;

    public const float OFFLINE_SLOW_HASH_PER_SECOND = 1e4;

    public const float OFFLINE_FAST_HASH_PER_SECOND = 1e10;

    public function __construct(
        public float $onlineThrottled,
        public float $onlineUnthrottled,
        public float $offlineSlowHash,
        public float $offlineFastHash,
    ) {
    }

    public static function fromGuesses(float $guesses): self
    {
        return new self(
            $guesses / self::ONLINE_THROTTLED_PER_SECOND,
            $guesses / self::ONLINE_UNTHROTTLED_PER_SECOND,
            $guesses / self::OFFLINE_SLOW_HASH_PER_SECOND,
            $guesses / self::OFFLINE_FAST_HASH_PER_SECOND,
        );
    }
}
