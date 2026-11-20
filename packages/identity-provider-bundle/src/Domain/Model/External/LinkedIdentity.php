<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\External;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * An external identity linked to an account, as a row of `phil_identity_linked`: the provider's name, the subject it
 * gives the person — never their tokens —, and when it was linked and last used. One subject of a provider is linked
 * to one account at most.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_identity_linked_subject', columns: ['provider', 'subject'])]
#[ORM\Index(name: 'phil_identity_linked_account', columns: ['account_id'])]
final class LinkedIdentity
{
    public const string TABLE = 'phil_identity_linked';

    public const string PROVIDER_PATTERN = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    public const int SUBJECT_MAX_LENGTH = 255;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: Account::ID_MAX_LENGTH)]
        private readonly string $accountId,
        #[ORM\Column(name: 'provider', length: 64)]
        private readonly string $provider,
        #[ORM\Column(name: 'subject', length: self::SUBJECT_MAX_LENGTH)]
        private readonly string $subject,
        #[ORM\Column(name: 'linked_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $linkedAt,
        #[ORM\Column(name: 'last_used_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    public static function link(string $accountId, string $provider, string $subject, DateTimeImmutable $now): self
    {
        if (1 !== preg_match(self::PROVIDER_PATTERN, $provider) || '' === $subject || \strlen($subject) > self::SUBJECT_MAX_LENGTH) {
            throw InvalidValue::because('A provider is a lowercase name of up to 64 characters, a subject 1 to 255 bytes.');
        }

        return new self(self::idOf($provider, $subject), $accountId, $provider, $subject, $now);
    }

    public static function idOf(string $provider, string $subject): string
    {
        return hash('sha256', $provider."\0".$subject);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function linkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function recordUse(DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }
}
