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
 * An OAuth 2 access token, with the refresh token that renews it, when it expires, and the scopes it was granted.
 */
final readonly class OAuthToken implements Credential
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public SecretValue $accessToken,
        public ?SecretValue $refreshToken = null,
        public ?DateTimeImmutable $expiresAt = null,
        public array $scopes = [],
    ) {
    }

    /**
     * @param list<string> $scopes
     *
     * @throws InvalidSecret when a token is empty
     */
    public static function of(#[SensitiveParameter] string $accessToken, #[SensitiveParameter] ?string $refreshToken = null, ?DateTimeImmutable $expiresAt = null, array $scopes = []): self
    {
        return new self(SecretValue::of($accessToken), null === $refreshToken ? null : SecretValue::of($refreshToken), $expiresAt, $scopes);
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidSecret
     */
    public static function fromPayload(array $payload): self
    {
        $access = $payload['access_token'] ?? null;
        $refresh = $payload['refresh_token'] ?? null;
        $expires = $payload['expires_at'] ?? null;
        $scopes = $payload['scopes'] ?? [];

        if (!\is_string($access) || (null !== $refresh && !\is_string($refresh)) || (null !== $expires && !\is_int($expires))) {
            throw InvalidSecret::because('An OAuth token payload needs an "access_token" string, and a "refresh_token" string and an "expires_at" timestamp when present.');
        }

        if (!\is_array($scopes) || !array_is_list($scopes) || array_filter($scopes, is_string(...)) !== $scopes) {
            throw InvalidSecret::because('The scopes of an OAuth token payload are a list of strings.');
        }

        return self::of($access, $refresh, null === $expires ? null : DateTimeImmutable::createFromTimestamp($expires), $scopes);
    }

    /**
     * Whether the token has expired, or will within the given number of seconds; never for a token without expiry.
     */
    public function expiresWithin(DateTimeImmutable $now, int $seconds): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt->getTimestamp() - $seconds <= $now->getTimestamp();
    }

    /**
     * The token a refresh returned, keeping this one's refresh token when the service did not issue a new one.
     */
    public function refreshedBy(self $fresh): self
    {
        return new self($fresh->accessToken, $fresh->refreshToken ?? $this->refreshToken, $fresh->expiresAt, $fresh->scopes);
    }

    #[Override]
    public function kind(): CredentialKind
    {
        return CredentialKind::OAuthToken;
    }

    #[Override]
    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    #[Override]
    public function payload(): array
    {
        return [
            'access_token' => $this->accessToken->reveal(),
            'refresh_token' => $this->refreshToken?->reveal(),
            'expires_at' => $this->expiresAt?->getTimestamp(),
            'scopes' => $this->scopes,
        ];
    }
}
