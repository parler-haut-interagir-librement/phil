<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;

/**
 * A token the provider issued, as a row of `phil_identity_token`: an access token by its `jti`, a refresh token by the
 * SHA-256 of its value; its family — every token from one authorization code —, client, account — none for a token
 * a client asked for itself by the `client_credentials` grant —, scopes, session, when and how the account
 * authenticated, when it was issued and expires, when a refresh token was rotated, when it was revoked, and the
 * resource it is for — null for the provider itself, whose userinfo it reads.
 *
 * A rotated refresh token presented again is a reuse: the whole family is revoked (OAuth 2.0 Security Best Current
 * Practice, RFC 9700, section 4.14).
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_token_family', columns: ['family'])]
#[ORM\Index(name: 'phil_identity_token_account', columns: ['account_id'])]
#[ORM\Index(name: 'phil_identity_token_expires_at', columns: ['expires_at'])]
final class IssuedToken
{
    public const string TABLE = 'phil_identity_token';

    /**
     * @param list<string> $scopes
     * @param list<string> $methods
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64)]
        private readonly string $id,
        #[ORM\Column(name: 'kind', length: 10, enumType: TokenKind::class)]
        private readonly TokenKind $kind,
        #[ORM\Column(name: 'family', length: 26, options: ['fixed' => true])]
        private readonly string $family,
        #[ORM\Column(name: 'client_id', length: 64)]
        private readonly string $clientId,
        #[ORM\Column(name: 'account_id', length: 26, nullable: true, options: ['fixed' => true])]
        private readonly ?string $accountId,
        #[ORM\Column(name: 'scopes', type: 'json')]
        private readonly array $scopes,
        #[ORM\Column(name: 'session_id', length: 43, nullable: true, options: ['fixed' => true])]
        private readonly ?string $sessionId,
        #[ORM\Column(name: 'auth_time')]
        private readonly DateTimeImmutable $authTime,
        #[ORM\Column(name: 'methods', type: 'json')]
        private readonly array $methods,
        #[ORM\Column(name: 'issued_at')]
        private readonly DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'expires_at')]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'rotated_at', nullable: true)]
        private ?DateTimeImmutable $rotatedAt = null,
        #[ORM\Column(name: 'revoked_at', nullable: true)]
        private ?DateTimeImmutable $revokedAt = null,
        #[ORM\Column(name: 'audience', length: 255, nullable: true)]
        private readonly ?string $audience = null,
    ) {
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $methods
     */
    public static function issue(string $id, TokenKind $kind, string $family, string $clientId, ?string $accountId, array $scopes, ?string $sessionId, DateTimeImmutable $authTime, array $methods, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt, ?string $audience = null): self
    {
        return new self($id, $kind, $family, $clientId, $accountId, $scopes, $sessionId, $authTime, $methods, $issuedAt, $expiresAt, null, null, $audience);
    }

    public static function idOfRefresh(string $value): string
    {
        return hash('sha256', $value);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): TokenKind
    {
        return $this->kind;
    }

    public function family(): string
    {
        return $this->family;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    /**
     * The account, null for a token of the `client_credentials` grant.
     */
    public function accountId(): ?string
    {
        return $this->accountId;
    }

    /**
     * The resource the token is for, null for the provider itself.
     */
    public function audience(): ?string
    {
        return $this->audience;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function authTime(): DateTimeImmutable
    {
        return $this->authTime;
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isRotated(): bool
    {
        return $this->rotatedAt instanceof DateTimeImmutable;
    }

    public function rotate(DateTimeImmutable $now): void
    {
        $this->rotatedAt ??= $now;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function revoke(DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    /**
     * Whether the token may still be used: not revoked, not rotated, not expired.
     */
    public function isActive(DateTimeImmutable $now): bool
    {
        return !$this->revokedAt instanceof DateTimeImmutable && !$this->rotatedAt instanceof DateTimeImmutable && $this->expiresAt > $now;
    }
}
