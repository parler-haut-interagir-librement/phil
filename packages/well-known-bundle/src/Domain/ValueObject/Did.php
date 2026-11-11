<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * A decentralized identifier (DID Core 1.0, section 3.1): `did:`, a method in lowercase letters and digits, `:` and an
 * identifier of letters, digits, `.`, `-`, `_`, percent-encoded bytes and `:`, which does not end in `:`.
 */
final readonly class Did
{
    public const int MAX_LENGTH = 2048;

    private const string SYNTAX = '/^did:([a-z0-9]+):(?:[A-Za-z0-9._-]|%[0-9A-Fa-f]{2}|:)*(?:[A-Za-z0-9._-]|%[0-9A-Fa-f]{2})$/D';

    private function __construct(
        public string $did,
        public string $method,
    ) {
    }

    /**
     * @param ?string $method the method the DID must have, such as `web`; any when `null`
     *
     * @throws InvalidValue when the text is not a DID, or not one of that method
     */
    public static function of(string $did, ?string $method = null): self
    {
        if (\strlen($did) > self::MAX_LENGTH || 1 !== preg_match(self::SYNTAX, $did, $matches)) {
            throw InvalidValue::because(\sprintf('"%s" is not a DID: "did:", a method, ":" and an identifier.', $did));
        }

        if (null !== $method && $method !== $matches[1]) {
            throw InvalidValue::because(\sprintf('"%s" is not a did:%s identifier.', $did, $method));
        }

        return new self($did, $matches[1]);
    }
}
