<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * An authorization code, as a row of `phil_identity_code`: the SHA-256 of the code — never the code —, the client,
 * the account, the exact redirect address, the scopes, the nonce, the PKCE challenge if any, when and how the
 * account authenticated, the session, the family its tokens will belong to, when it expires and when it was used.
 *
 * A code is used once: a second use revokes every token of its family (RFC 6749, section 4.1.2).
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_code_expires_at', columns: ['expires_at'])]
final class AuthorizationCode
{
    public const string TABLE = 'phil_identity_code';

    /**
     * @param list<string> $scopes
     * @param list<string> $methods
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'client_id', length: 64)]
        private readonly string $clientId,
        #[ORM\Column(name: 'account_id', length: 26, options: ['fixed' => true])]
        private readonly string $accountId,
        #[ORM\Column(name: 'redirect_uri', type: 'text')]
        private readonly string $redirectUri,
        #[ORM\Column(name: 'scopes', type: 'json')]
        private readonly array $scopes,
        #[ORM\Column(name: 'nonce', length: 255, nullable: true)]
        private readonly ?string $nonce,
        #[ORM\Column(name: 'code_challenge', length: 128, nullable: true)]
        private readonly ?string $codeChallenge,
        #[ORM\Column(name: 'auth_time')]
        private readonly DateTimeImmutable $authTime,
        #[ORM\Column(name: 'methods', type: 'json')]
        private readonly array $methods,
        #[ORM\Column(name: 'session_id', length: 43, nullable: true, options: ['fixed' => true])]
        private readonly ?string $sessionId,
        #[ORM\Column(name: 'family', length: 26, options: ['fixed' => true])]
        private readonly string $family,
        #[ORM\Column(name: 'expires_at')]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'used_at', nullable: true)]
        private ?DateTimeImmutable $usedAt = null,
    ) {
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $methods
     */
    public static function issue(string $code, string $clientId, string $accountId, string $redirectUri, array $scopes, ?string $nonce, ?string $codeChallenge, DateTimeImmutable $authTime, array $methods, ?string $sessionId, string $family, DateTimeImmutable $expiresAt): self
    {
        return new self(self::idOf($code), $clientId, $accountId, $redirectUri, $scopes, $nonce, $codeChallenge, $authTime, $methods, $sessionId, $family, $expiresAt);
    }

    public static function idOf(string $code): string
    {
        return hash('sha256', $code);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function redirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function nonce(): ?string
    {
        return $this->nonce;
    }

    public function codeChallenge(): ?string
    {
        return $this->codeChallenge;
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

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function family(): string
    {
        return $this->family;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isUsed(): bool
    {
        return $this->usedAt instanceof DateTimeImmutable;
    }

    public function use(DateTimeImmutable $now): void
    {
        $this->usedAt ??= $now;
    }
}
