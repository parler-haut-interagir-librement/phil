<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * What an account allowed a client, as a row of `phil_identity_consent`: the scopes granted and when. A request for
 * a scope outside them asks again; withdrawing the consent revokes the client's tokens.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_consent_account', columns: ['account_id'])]
final class Consent
{
    public const string TABLE = 'phil_identity_consent';

    /**
     * @param list<string> $scopes
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: 26, options: ['fixed' => true])]
        private readonly string $accountId,
        #[ORM\Column(name: 'client_id', length: 64)]
        private readonly string $clientId,
        #[ORM\Column(name: 'scopes', type: 'json')]
        private array $scopes,
        #[ORM\Column(name: 'granted_at')]
        private DateTimeImmutable $grantedAt,
    ) {
    }

    /**
     * @param list<string> $scopes
     */
    public static function grant(string $accountId, string $clientId, array $scopes, DateTimeImmutable $now): self
    {
        return new self(self::idOf($accountId, $clientId), $accountId, $clientId, array_values(array_unique($scopes)), $now);
    }

    public static function idOf(string $accountId, string $clientId): string
    {
        return hash('sha256', $accountId."\0".$clientId);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param list<string> $scopes
     */
    public function covers(array $scopes): bool
    {
        return [] === array_diff($scopes, $this->scopes);
    }

    /**
     * @param list<string> $scopes
     */
    public function extend(array $scopes, DateTimeImmutable $now): void
    {
        $this->scopes = array_values(array_unique([...$this->scopes, ...$scopes]));
        $this->grantedAt = $now;
    }

    public function grantedAt(): DateTimeImmutable
    {
        return $this->grantedAt;
    }
}
