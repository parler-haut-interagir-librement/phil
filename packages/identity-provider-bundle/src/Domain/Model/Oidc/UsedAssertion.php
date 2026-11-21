<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A client assertion of `private_key_jwt` already presented, as a row of `phil_identity_assertion`: the SHA-256 of its
 * client and its `jti`, kept until the assertion expires, so that the same assertion is refused the second time
 * (RFC 7523, section 3, item 7). The key of the row is what makes two requests presenting it at once refused but one.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_assertion_expires_at', columns: ['expires_at'])]
final class UsedAssertion
{
    public const string TABLE = 'phil_identity_assertion';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'expires_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public static function record(string $clientId, string $jti, DateTimeImmutable $expiresAt): self
    {
        return new self(self::idOf($clientId, $jti), $expiresAt);
    }

    public static function idOf(string $clientId, string $jti): string
    {
        return hash('sha256', $clientId."\0".$jti);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
