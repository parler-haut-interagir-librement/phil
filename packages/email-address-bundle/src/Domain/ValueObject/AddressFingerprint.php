<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain\ValueObject;

use InvalidArgumentException;
use SensitiveParameter;
use Stringable;

/**
 * A keyed hash of an address in its normalized form: the same mailbox gives the same fingerprint under the same key,
 * and the fingerprint does not give the address back. Messages and logs carry it instead of the address.
 */
final readonly class AddressFingerprint implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param string $normalized the address in the normalized form of `EmailAddresses::normalize()`
     * @param string $key        the key of the fingerprints, derived for that usage from the application's secret
     *
     * @throws InvalidArgumentException when the key is empty
     */
    public static function of(#[SensitiveParameter] string $normalized, #[SensitiveParameter] string $key): self
    {
        if ('' === $key) {
            throw new InvalidArgumentException('An address fingerprint needs a key.');
        }

        return new self(hash_hmac('sha256', $normalized, $key));
    }

    /**
     * A fingerprint read back from a message or a store.
     *
     * @throws InvalidArgumentException when the text is not 64 lowercase hexadecimal digits
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $value)) {
            throw new InvalidArgumentException('An address fingerprint is 64 lowercase hexadecimal digits.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
