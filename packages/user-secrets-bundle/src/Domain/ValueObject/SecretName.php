<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\ValueObject;

use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;

/**
 * The name an owner gives a secret, unique among their secrets: `openai`, `github.work`, `dropbox-archive`.
 *
 * Lowercase letters, digits, `_`, `.` and `-`, starting with a letter, at most 64 characters.
 */
final readonly class SecretName
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidSecret
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidSecret::because(\sprintf('"%s" is not a secret name: lowercase letters, digits, "_", "." and "-", starting with a letter, at most 64 characters.', $value));
        }

        return new self($value);
    }
}
