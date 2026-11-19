<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\ValueObject;

use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;

/**
 * The third-party service a secret opens, as the application names it: `github`, `openai`. It chooses the token
 * refresher of an OAuth token; two secrets of one owner may open the same service.
 *
 * Lowercase letters, digits, `_`, `.` and `-`, starting with a letter, at most 64 characters.
 */
final readonly class ServiceName
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
            throw InvalidSecret::because(\sprintf('"%s" is not a service name: lowercase letters, digits, "_", "." and "-", starting with a letter, at most 64 characters.', $value));
        }

        return new self($value);
    }
}
