<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\ValueObject;

use LogicException;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use SensitiveParameter;

/**
 * A secret string — an API key, an access or refresh token — that does not show itself.
 *
 * `var_dump()` and `print_r()` print `[redacted]`, it is a sensitive parameter in stack traces, and it refuses to be
 * serialized, so it cannot reach a log, a cache or a session by accident. `reveal()` is the one way to the string,
 * for the moment it is sent to the service.
 */
final readonly class SecretValue
{
    private function __construct(
        private string $value,
    ) {
    }

    /**
     * @throws InvalidSecret when the value is empty
     */
    public static function of(#[SensitiveParameter] string $value): self
    {
        if ('' === $value) {
            throw InvalidSecret::because('A secret value cannot be empty.');
        }

        return new self($value);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * Compares in constant time.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A secret value is never serialized.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('A secret value is never unserialized.');
    }
}
