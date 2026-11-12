<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\SaltProvider;
use Phil\IpAnonymizer\Exception\InvalidOption;
use Phil\IpAnonymizer\Exception\InvalidSalt;
use Phil\IpAnonymizer\Internal\SaltPeriod;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * A salt per period of the clock, derived from a master key: a new salt each UTC day by default, the same in every
 * process and on every server, with nothing stored. This derivation is optional: {@see ForgottenSalt} is the provider
 * to reach for, unless nothing may be stored.
 *
 * The salt of a period is `sodium_crypto_kdf_derive_from_key()` of the master key with the period's index — the
 * number of whole periods since the Unix epoch — as the subkey identifier. Its identifier is
 * `<period seconds>-<index>`, `86400-20400` for 2025-11-08 with daily periods.
 *
 * The digests of one address are unlinkable from one period to the next **to whoever does not hold the master key**.
 * Whoever holds it derives every past salt again: an application that must not be able to link them itself uses
 * {@see ForgottenSalt}.
 */
final readonly class RotatingSalt implements SaltProvider
{
    public const int DAY = 86_400;

    public const int KEY_BYTES = \SODIUM_CRYPTO_KDF_KEYBYTES;

    private const string CONTEXT = 'ipanonym';

    /**
     * @var positive-int
     */
    private int $periodSeconds;

    /**
     * @throws InvalidSalt   when the master key is not 32 bytes long
     * @throws InvalidOption when the period is shorter than a second
     */
    public function __construct(
        #[SensitiveParameter]
        private string $masterKey,
        private ClockInterface $clock,
        int $periodSeconds = self::DAY,
    ) {
        if (self::KEY_BYTES !== \strlen($masterKey)) {
            throw InvalidSalt::because(\sprintf('The master key of a rotating salt is %d bytes long.', self::KEY_BYTES));
        }

        $this->periodSeconds = SaltPeriod::length($periodSeconds);
    }

    /**
     * @throws InvalidOption when the clock reads an instant before the Unix epoch
     */
    #[Override]
    public function current(): Salt
    {
        $period = SaltPeriod::at($this->clock->now(), $this->periodSeconds);

        return Salt::of(
            $period->saltId,
            sodium_crypto_kdf_derive_from_key(Salt::RECOMMENDED_BYTES, $period->index, self::CONTEXT, $this->masterKey),
        );
    }

    /**
     * @return array{periodSeconds: int, masterKey: string}
     */
    public function __debugInfo(): array
    {
        return ['periodSeconds' => $this->periodSeconds, 'masterKey' => '[redacted]'];
    }
}
