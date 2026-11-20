<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Totp;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use SensitiveParameter;

/**
 * The authenticator application of an account, as a row of `phil_identity_totp`: its secret — encrypted by the key
 * management service when one is configured —, when its first code confirmed it, and the last counter accepted, so
 * that a code is never accepted twice. Until it is confirmed, it does not count as a second factor. Only its account
 * is a read-only property: the store reads the row again over the object before accepting a code, and the secret may
 * have been replaced by a new enrolment meanwhile.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class TotpCredential
{
    public const string TABLE = 'phil_identity_totp';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'account_id', length: Account::ID_MAX_LENGTH)]
        private readonly string $accountId,
        #[ORM\Column(name: 'secret', type: 'text')]
        private string $secret,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'confirmed_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $confirmedAt = null,
        #[ORM\Column(name: 'last_counter', type: 'bigint', nullable: true)]
        private ?int $lastCounter = null,
    ) {
    }

    /**
     * @param string $secret the secret as the store keeps it: encrypted, or base32
     */
    public static function enroll(string $accountId, #[SensitiveParameter] string $secret, DateTimeImmutable $now): self
    {
        return new self($accountId, $secret, $now);
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function storedSecret(): string
    {
        return $this->secret;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt instanceof DateTimeImmutable;
    }

    public function lastCounter(): ?int
    {
        return $this->lastCounter;
    }

    public function accept(int $counter, DateTimeImmutable $now): void
    {
        $this->lastCounter = $counter;
        $this->confirmedAt ??= $now;
    }
}
