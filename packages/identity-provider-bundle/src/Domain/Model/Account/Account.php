<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Account;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use SensitiveParameter;

/**
 * An account, as a row of `phil_identity_account`: its identifier, its email address and when it was verified, the
 * name it shows, the Argon2id hash of its password — none for an account that signs in with passkeys only —, a phone
 * number and when it was verified, its roles, the failed sign-ins in a row and the lock they lead to, and when it was
 * disabled. Nothing else: a profile belongs to the application.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_identity_account_email', columns: ['email'])]
final class Account
{
    public const string TABLE = 'phil_identity_account';

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
        #[ORM\Column(name: 'id', length: 26, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'email', length: self::EMAIL_MAX_LENGTH)]
        private string $email,
        // Not read-only: the store reads the row again over the object when it applies a change under a lock.
        #[ORM\Column(name: 'created_at')]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'display_name', length: self::NAME_MAX_LENGTH, nullable: true)]
        private ?string $displayName = null,
        #[ORM\Column(name: 'email_verified_at', nullable: true)]
        private ?DateTimeImmutable $emailVerifiedAt = null,
        #[ORM\Column(name: 'password_hash', length: 255, nullable: true)]
        private ?string $passwordHash = null,
        #[ORM\Column(name: 'password_changed_at', nullable: true)]
        private ?DateTimeImmutable $passwordChangedAt = null,
        #[ORM\Column(name: 'phone', length: self::PHONE_MAX_LENGTH, nullable: true)]
        private ?string $phone = null,
        #[ORM\Column(name: 'phone_verified_at', nullable: true)]
        private ?DateTimeImmutable $phoneVerifiedAt = null,
        #[ORM\Column(name: 'roles', type: 'json')]
        private array $roles = [],
        #[ORM\Column(name: 'failed_sign_ins', options: ['default' => 0])]
        private int $failedSignIns = 0,
        #[ORM\Column(name: 'locked_until', nullable: true)]
        private ?DateTimeImmutable $lockedUntil = null,
        #[ORM\Column(name: 'disabled_at', nullable: true)]
        private ?DateTimeImmutable $disabledAt = null,
    ) {
    }

    public static function register(string $id, string $email, DateTimeImmutable $now, ?string $displayName = null): self
    {
        if (26 !== \strlen($id)) {
            throw InvalidValue::because('An account identifier is a ULID of 26 characters.');
        }

        return new self($id, self::normalizeEmail($email), $now, self::normalizeName($displayName));
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
     */
    public function changeEmail(string $email): void
    {
        $email = self::normalizeEmail($email);

        if ($email !== $this->email) {
            $this->email = $email;
            $this->emailVerifiedAt = null;
        }
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

    public function disabledAt(): ?DateTimeImmutable
    {
        return $this->disabledAt;
    }

    public function isDisabled(): bool
    {
        return $this->disabledAt instanceof DateTimeImmutable;
    }

    public function disable(DateTimeImmutable $now): void
    {
        $this->disabledAt ??= $now;
    }

    public function enable(): void
    {
        $this->disabledAt = null;
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
