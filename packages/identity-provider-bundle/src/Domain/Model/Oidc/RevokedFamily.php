<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A family of tokens revoked, as a row of `phil_identity_revoked_family`: its identifier, when it was revoked, and how
 * long the mark is kept — as long as a token issued at that moment could live.
 *
 * The mark is written before the tokens of the family are revoked, and read by the request that issues tokens in the
 * family after it saved them: whichever of the two comes second sees the work of the first, so a token saved while the
 * family was being revoked never stays active (RFC 9700, section 4.14).
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_revoked_family_expires_at', columns: ['expires_at'])]
final class RevokedFamily
{
    public const string TABLE = 'phil_identity_revoked_family';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'family', length: 26, options: ['fixed' => true])]
        private readonly string $family,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $revokedAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function mark(string $family, DateTimeImmutable $revokedAt, DateTimeImmutable $expiresAt): self
    {
        return new self($family, $revokedAt, $expiresAt);
    }

    public function family(): string
    {
        return $this->family;
    }

    public function revokedAt(): DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
