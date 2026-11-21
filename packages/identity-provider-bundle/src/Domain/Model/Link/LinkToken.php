<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Link;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The token of a link sent by email, kept when `links.mode` is `stored`, as a row of `phil_identity_link_token`: a
 * keyed hash of the token as key — the token itself is only in the email —, the account and the purpose of the link,
 * the fingerprint of the account it was signed over, and when it expires. The row is removed when the link is used or
 * revoked, so that a link works once.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_link_token_account', columns: ['account_id'])]
#[ORM\Index(name: 'phil_identity_link_token_expires', columns: ['expires_at'])]
final class LinkToken
{
    public const string TABLE = 'phil_identity_link_token';

    public const int PURPOSE_MAX_LENGTH = 16;

    public const int FINGERPRINT_MAX_LENGTH = 128;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: Account::ID_MAX_LENGTH)]
        private readonly string $accountId,
        #[ORM\Column(name: 'purpose', length: self::PURPOSE_MAX_LENGTH)]
        private readonly string $purpose,
        #[ORM\Column(name: 'fingerprint', length: self::FINGERPRINT_MAX_LENGTH)]
        private readonly string $fingerprint,
        #[ORM\Column(name: 'expires_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param string $id the keyed hash of the token, 64 hexadecimal digits
     *
     * @throws InvalidValue when a part is out of bounds
     */
    public static function issue(string $id, string $accountId, string $purpose, string $fingerprint, DateTimeImmutable $expiresAt, DateTimeImmutable $now): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $id) || '' === $purpose || \strlen($purpose) > self::PURPOSE_MAX_LENGTH || \strlen($fingerprint) > self::FINGERPRINT_MAX_LENGTH) {
            throw InvalidValue::because('A link token has a hash of 64 hexadecimal digits, a purpose of 1 to 16 bytes and a fingerprint of at most 128.');
        }

        return new self($id, $accountId, $purpose, $fingerprint, $expiresAt, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
