<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\Contract\SaltProvider;
use Phil\IpAnonymizer\Exception\InvalidOption;

/**
 * The keyed digest of an address: BLAKE2b (`sodium_crypto_generichash()`) of its bytes, keyed by the current salt.
 *
 * The same address gives the same digest for as long as the salt lasts, so that visits can be counted or a client
 * rate-limited without the address being kept; another salt gives unrelated digests. The text is
 * `<salt id>:<hex digest>`, 16 bytes of digest by default, up to 64.
 */
final readonly class KeyedHash implements IpAnonymizer
{
    public const int DEFAULT_LENGTH = 16;

    public const int MIN_LENGTH = \SODIUM_CRYPTO_GENERICHASH_BYTES_MIN;

    public const int MAX_LENGTH = \SODIUM_CRYPTO_GENERICHASH_BYTES_MAX;

    /**
     * @throws InvalidOption when the length of the digest is outside 16 to 64 bytes
     */
    public function __construct(
        private SaltProvider $salts,
        public int $length = self::DEFAULT_LENGTH,
    ) {
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidOption::because(\sprintf('A digest is %d to %d bytes long; %d is not.', self::MIN_LENGTH, self::MAX_LENGTH, $length));
        }
    }

    public function digest(IpAddress $address): IpDigest
    {
        $salt = $this->salts->current();

        return new IpDigest($salt->id, sodium_crypto_generichash($address->bytes, $salt->bytes, $this->length));
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return $this->digest($address)->toString();
    }
}
