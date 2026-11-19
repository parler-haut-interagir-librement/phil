<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Credential;

use DateTimeImmutable;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\Credential;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretValue;
use SensitiveParameter;

/**
 * A key a service issued to the person, sent as is with each call. It does not expire as far as the bundle knows.
 */
final readonly class ApiKey implements Credential
{
    public function __construct(
        public SecretValue $key,
    ) {
    }

    /**
     * @throws InvalidSecret when the key is empty
     */
    public static function of(#[SensitiveParameter] string $key): self
    {
        return new self(SecretValue::of($key));
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidSecret
     */
    public static function fromPayload(array $payload): self
    {
        $key = $payload['key'] ?? null;

        if (!\is_string($key)) {
            throw InvalidSecret::because('An API key payload has no "key" string.');
        }

        return self::of($key);
    }

    #[Override]
    public function kind(): CredentialKind
    {
        return CredentialKind::ApiKey;
    }

    #[Override]
    public function expiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    #[Override]
    public function payload(): array
    {
        return ['key' => $this->key->reveal()];
    }
}
