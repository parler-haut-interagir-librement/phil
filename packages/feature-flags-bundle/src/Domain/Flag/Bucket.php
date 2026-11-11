<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The stable bucket, 0 to 99, that a targeting key falls into for a given flag.
 *
 * **Deterministic and salted by the flag name.** The bucket is `murmur3a("<flag>:<key>") mod 100`, the
 * 32-bit MurmurHash3 that PHP ships in `ext/hash`, on the same `group:identifier` shape as the gradual
 * rollout of the Unleash SDKs. The same key gets the same bucket for the same flag on every request and on
 * every server, so a rollout does not flicker; a different flag gets an independent bucket, so the same
 * tenants are not always the first ones in.
 *
 * **Changing this function is a one-way door**: every running rollout is reshuffled at once. The unit tests
 * pin known values so that such a change cannot pass unnoticed.
 *
 * `2^32 mod 100 = 96`, so buckets 0 to 95 are one part in 43 million more likely than 96 to 99: a bias far
 * below anything a rollout can observe.
 */
final readonly class Bucket
{
    public const int COUNT = 100;

    private function __construct(
        public int $value,
    ) {
    }

    public static function of(FlagName $flag, string $targetingKey): self
    {
        if ('' === $targetingKey) {
            throw InvalidValue::because('A targeting key cannot be empty: every empty key would share one bucket.');
        }

        return self::hashed($flag->value.':'.$targetingKey);
    }

    /**
     * The bucket of a targeting key in the experiment of a flag: `murmur3a("<flag>:variant:<key>") mod 100`.
     *
     * Salted apart from {@see self::of()}, so that the tenants admitted first by the rollout of a flag are not
     * also the ones that fall into its first variant. A flag name holds no `:`, so the salt cannot be confused
     * with another flag's. **The same one-way door**: changing it moves every running experiment's cohorts.
     */
    public static function forVariant(FlagName $flag, string $targetingKey): self
    {
        if ('' === $targetingKey) {
            throw InvalidValue::because('A targeting key cannot be empty: every empty key would share one bucket.');
        }

        return self::hashed($flag->value.':variant:'.$targetingKey);
    }

    private static function hashed(string $input): self
    {
        return new self(hexdec(hash('murmur3a', $input)) % self::COUNT);
    }
}
