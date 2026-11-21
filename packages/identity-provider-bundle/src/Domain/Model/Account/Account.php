<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Account;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Account\AccountState;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use SensitiveParameter;

/**
 * An account, as a row of `phil_identity_account`: its identifier, its sign-in address, the canonical form of that
 * address — unique, so that two spellings of one mailbox are one account — and when it was verified, the name it shows,
 * the Argon2id hash of its password — none for an account that signs in with passkeys only —, a phone number and when
 * it was verified, its roles, the failed sign-ins in a row and the lock they lead to, and its state and when it last
 * changed. Nothing else: a profile belongs to the application, and the addresses of other purposes are
 * `AccountAddress` rows.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_identity_account_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'phil_identity_account_canonical', columns: ['email_canonical'])]
final class Account
{
    public const string TABLE = 'phil_identity_account';

    /** The longest identifier, the 36 characters of a UUID. */
    public const int ID_MAX_LENGTH = 36;

    /** What an identifier holds: letters, digits and hyphens, as a ULID or a UUID writes them. */
    public const string ID_PATTERN = '/^[0-9A-Za-z-]{1,36}$/D';

    public const int EMAIL_MAX_LENGTH = 180;

    public const int NAME_MAX_LENGTH = 100;

    public const int PHONE_MAX_LENGTH = 16;

    /** E.164: a plus sign and up to fifteen digits, the first not zero. */
    public const string PHONE_PATTERN = '/^\+[1-9]\d{6,14}$/D';

    /**
     * @param list<string> $roles
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: self::ID_MAX_LENGTH)]
        private readonly string $id,
        #[ORM\Column(name: 'email', length: self::EMAIL_MAX_LENGTH)]
        private string $email,
        #[ORM\Column(name: 'email_canonical', length: self::EMAIL_MAX_LENGTH)]
        private string $emailCanonical,
        // Not read-only: the store reads the row again over the object when it applies a change under a lock.
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'display_name', length: self::NAME_MAX_LENGTH, nullable: true)]
        private ?string $displayName = null,
        #[ORM\Column(name: 'email_verified_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $emailVerifiedAt = null,
        #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
        private ?string $passwordHash = null,
        #[ORM\Column(name: 'password_changed_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $passwordChangedAt = null,
        #[ORM\Column(name: 'phone', length: self::PHONE_MAX_LENGTH, nullable: true)]
        private ?string $phone = null,
        #[ORM\Column(name: 'phone_verified_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $phoneVerifiedAt = null,
        #[ORM\Column(name: 'roles', type: 'json')]
        private array $roles = [],
        #[ORM\Column(name: 'failed_sign_ins', options: ['default' => 0])]
        private int $failedSignIns = 0,
        #[ORM\Column(name: 'locked_until', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $lockedUntil = null,
        #[ORM\Column(name: 'state', length: AccountState::MAX_LENGTH, enumType: AccountState::class, options: ['default' => 'active'])]
        private AccountState $state = AccountState::Active,
        #[ORM\Column(name: 'state_changed_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $stateChangedAt = null,
    ) {
    }

    /**
     * @param ?string $canonical the canonical form of the address, as `AddressCanonicalizer` gives it; the address
     *                           normalized when null
     */
    public static function register(string $id, string $email, DateTimeImmutable $now, ?string $displayName = null, ?string $canonical = null): self
    {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidValue::because('An account identifier holds 1 to 36 letters, digits and hyphens.');
        }

        $email = self::normalizeEmail($email);

        return new self($id, $email, $canonical ?? $email, $now, self::normalizeName($displayName));
    }

    /**
     * The address in lower case and trimmed: two spellings of one address are one account.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if ('' === $email || mb_strlen($email) > self::EMAIL_MAX_LENGTH || 1 !== preg_match('/^[^@\s]+@[^@\s]+$/uD', $email)) {
            throw InvalidValue::because('An email address has one "@" and at most 180 characters.');
        }

        return $email;
    }

    /**
     * @return non-empty-string
     */
    public function id(): string
    {
        \assert('' !== $this->id);

        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    /**
     * The canonical form of the sign-in address, unique among the accounts.
     */
    public function emailCanonical(): string
    {
        return $this->emailCanonical;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function rename(?string $displayName): void
    {
        $this->displayName = self::normalizeName($displayName);
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof DateTimeImmutable;
    }

    public function verifyEmail(DateTimeImmutable $now): void
    {
        $this->emailVerifiedAt ??= $now;
    }

    /**
     * A new address, unverified until its own link is followed.
     *
     * @param ?string $canonical the canonical form of the address; the address normalized when null
     */
    public function changeEmail(string $email, ?string $canonical = null): void
    {
        $email = self::normalizeEmail($email);

        if ($email !== $this->email) {
            $this->email = $email;
            $this->emailVerifiedAt = null;
        }

        $this->emailCanonical = $canonical ?? $email;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function hasPassword(): bool
    {
        return null !== $this->passwordHash;
    }

    public function passwordChangedAt(): ?DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function changePassword(#[SensitiveParameter] ?string $hash, DateTimeImmutable $now): void
    {
        $this->passwordHash = $hash;
        $this->passwordChangedAt = $now;
        $this->failedSignIns = 0;
        $this->lockedUntil = null;
    }

    /**
     * The same password hashed again with stronger settings, as the password hasher asks on a sign-in: nothing else
     * changes, and links signed over the date of the last change keep working.
     */
    public function rehashPassword(#[SensitiveParameter] string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function phoneVerifiedAt(): ?DateTimeImmutable
    {
        return $this->phoneVerifiedAt;
    }

    public function verifyPhone(string $phone, DateTimeImmutable $now): void
    {
        if (1 !== preg_match(self::PHONE_PATTERN, $phone)) {
            throw InvalidValue::because('A phone number is written in E.164, such as +15145550123.');
        }

        $this->phone = $phone;
        $this->phoneVerifiedAt = $now;
    }

    public function removePhone(): void
    {
        $this->phone = null;
        $this->phoneVerifiedAt = null;
    }

    /**
     * @return non-empty-list<string> `ROLE_USER` first
     */
    public function roles(): array
    {
        return array_values(array_unique(['ROLE_USER', ...$this->roles]));
    }

    /**
     * @param list<string> $roles
     */
    public function grantRoles(array $roles): void
    {
        $this->roles = array_values(array_unique(array_diff($roles, ['ROLE_USER'])));
    }

    public function failedSignIns(): int
    {
        return $this->failedSignIns;
    }

    public function lockedUntil(): ?DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function isLocked(DateTimeImmutable $now): bool
    {
        return $this->lockedUntil instanceof DateTimeImmutable && $this->lockedUntil > $now;
    }

    public function recordFailedSignIn(DateTimeImmutable $now, LockoutPolicy $policy): void
    {
        ++$this->failedSignIns;
        $this->lockedUntil = $policy->lockedUntil($this->failedSignIns, $now);
    }

    public function recordSignIn(): void
    {
        $this->failedSignIns = 0;
        $this->lockedUntil = null;
    }

    public function state(): AccountState
    {
        return $this->state;
    }

    /**
     * When the state last changed; null for an account active since it was registered.
     */
    public function stateChangedAt(): ?DateTimeImmutable
    {
        return $this->stateChangedAt;
    }

    /**
     * @return bool whether the state changed
     */
    public function changeState(AccountState $state, DateTimeImmutable $now): bool
    {
        if ($state === $this->state) {
            return false;
        }

        $this->state = $state;
        $this->stateChangedAt = $now;

        return true;
    }

    /**
     * When the account stopped signing in; null for an active account.
     */
    public function disabledAt(): ?DateTimeImmutable
    {
        return $this->state->signsIn() ? null : $this->stateChangedAt;
    }

    /**
     * Whether the account is refused at sign-in: any state but `active`.
     */
    public function isDisabled(): bool
    {
        return !$this->state->signsIn();
    }

    /**
     * Suspends an active account; an account in another state keeps it.
     */
    public function disable(DateTimeImmutable $now): void
    {
        if ($this->state->signsIn()) {
            $this->changeState(AccountState::Suspended, $now);
        }
    }

    public function enable(DateTimeImmutable $now): void
    {
        $this->changeState(AccountState::Active, $now);
    }

    private static function normalizeName(?string $name): ?string
    {
        $name = null === $name ? '' : trim($name);

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidValue::because('A display name holds at most 100 characters.');
        }

        return '' === $name ? null : $name;
    }
}
