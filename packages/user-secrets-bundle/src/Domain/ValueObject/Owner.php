<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\ValueObject;

use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;

/**
 * The person a secret belongs to, by the opaque identifier the application knows them by — a UUID, an OIDC `sub`, a
 * member number.
 *
 * The bundle never parses it. It refuses only what no store can index on: an empty identifier, one longer than 255
 * bytes, or one carrying whitespace or control characters.
 */
final readonly class Owner
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidSecret
     */
    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidSecret::because(\sprintf('An owner is 1 to %d bytes without whitespace or control characters.', self::MAX_LENGTH));
        }

        return new self($value);
    }
}
