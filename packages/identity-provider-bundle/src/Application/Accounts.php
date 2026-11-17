<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Accounts: registration, the address and its verification, the name, the password — hashed with Argon2id by the
 * password hasher of Symfony Security, checked by `PasswordRules` —, failed sign-ins and the lock they lead to.
 *
 * Registration with an address already known creates nothing and says nothing different: the page answers the same,
 * and an event lets the application warn the owner of the address, so that the form does not tell who has an account.
 *
 * A new password closes the account's other sessions and the tokens issued in them; a reset closes them all. A reset
 * link is used once: its password is set only while the account still has the password the link was signed over,
 * read again under a lock of its row. Failed sign-ins are counted the same way, so that two at once count twice.
 */
final readonly class Accounts
{
    public function __construct(
        private AccountRepository $accounts,
        private UserPasswordHasherInterface $hasher,
        private PasswordRules $rules,
        private Journal $journal,
        private ClockInterface $clock,
        private LockoutPolicy $lockout = new LockoutPolicy(),
        private ?Sessions $sessions = null,
    ) {
    }

    /**
     * A new account, null when the address already has one.
     *
     * @throws Refused when the address is out of shape or the password breaks a rule
     */
    public function register(string $email, #[SensitiveParameter] ?string $password, ?string $displayName = null): ?Account
    {
        try {
            $normalized = Account::normalizeEmail($email);
        } catch (InvalidValue) {
            throw Refused::because('email.invalid');
        }

        if (null !== $password && [] !== $problems = $this->rules->problems($password, email: $normalized)) {
            throw Refused::because(...$problems);
        }

        $known = $this->accounts->findByEmail($normalized);

        if ($known instanceof Account) {
            $this->journal->record(IdentityEventType::RegistrationForKnownEmail, $known->id());

            return null;
        }

        $now = $this->clock->now();

        try {
            $account = Account::register(new Ulid()->toBase32(), $normalized, $now, $displayName);
        } catch (InvalidValue) {
            throw Refused::because('name.too_long');
        }

        if (null !== $password) {
            $account->changePassword($this->hasher->hashPassword(new AccountUser($account), $password), $now);
        }

        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::AccountRegistered, $account->id());

        return $account;
    }

    public function get(string $id): ?Account
    {
        return $this->accounts->get($id);
    }

    public function findByEmail(string $email): ?Account
    {
        return $this->accounts->findByEmail($email);
    }

    public function verifyEmail(Account $account): void
    {
        if ($account->isEmailVerified()) {
            return;
        }

        $account->verifyEmail($this->clock->now());
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::EmailVerified, $account->id());
    }

    /**
     * @throws Refused when the address is out of shape or another account has it
     */
    public function changeEmail(Account $account, string $email): void
    {
        try {
            $normalized = Account::normalizeEmail($email);
        } catch (InvalidValue) {
            throw Refused::because('email.invalid');
        }

        if ($normalized === $account->email()) {
            return;
        }

        if ($this->accounts->findByEmail($normalized) instanceof Account) {
            throw Refused::because('email.taken');
        }

        $account->changeEmail($normalized);
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::EmailChanged, $account->id());
    }

    /**
     * @throws Refused when the name is longer than 100 characters
     */
    public function rename(Account $account, ?string $displayName): void
    {
        try {
            $account->rename($displayName);
        } catch (InvalidValue) {
            throw Refused::because('name.too_long');
        }

        $this->accounts->save($account);
    }

    /**
     * Sets a new password; the current one is checked when the account has one and it is given. Every session of the
     * account is closed but the one kept, the one the change is made in.
     *
     * @throws Refused when the current password does not match or the new one breaks a rule
     */
    public function changePassword(Account $account, #[SensitiveParameter] string $new, #[SensitiveParameter] ?string $current = null, ?string $keepSession = null): void
    {
        if (null !== $current && $account->hasPassword() && !$this->hasher->isPasswordValid(new AccountUser($account), $current)) {
            throw Refused::because('password.current_invalid');
        }

        if ([] !== $problems = $this->rules->problems($new, $account)) {
            throw Refused::because(...$problems);
        }

        $account->changePassword($this->hasher->hashPassword(new AccountUser($account), $new), $this->clock->now());
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::PasswordChanged, $account->id());
        $this->sessions?->closeAll($account, $keepSession);
    }

    /**
     * Sets the password a reset link asked for, and verifies the address the link reached, once: only while the stored
     * account still has the password the link was signed over — the one of the account given. Every session of the
     * account is closed.
     *
     * @return bool false when another use of the link, or another change of the password, came first
     *
     * @throws Refused when the new password breaks a rule
     */
    public function resetPassword(Account $account, #[SensitiveParameter] string $new): bool
    {
        if ([] !== $problems = $this->rules->problems($new, $account)) {
            throw Refused::because(...$problems);
        }

        $signedHash = $account->passwordHash();
        $signedAt = $account->passwordChangedAt()?->format('U.u');
        $hash = $this->hasher->hashPassword(new AccountUser($account), $new);
        $now = $this->clock->now();
        $verifies = !$account->isEmailVerified();
        $applied = $this->accounts->apply($account, static function (Account $stored) use ($signedHash, $signedAt, $hash, $now): bool {
            if ($stored->passwordHash() !== $signedHash || $stored->passwordChangedAt()?->format('U.u') !== $signedAt) {
                return false;
            }

            $stored->changePassword($hash, $now);

            if (!$stored->isEmailVerified()) {
                $stored->verifyEmail($now);
            }

            return true;
        });

        if (!$applied) {
            return false;
        }

        $this->journal->record(IdentityEventType::PasswordChanged, $account->id(), ['reset' => true]);

        if ($verifies) {
            $this->journal->record(IdentityEventType::EmailVerified, $account->id());
        }

        $this->sessions?->closeAll($account);

        return true;
    }

    public function removePassword(Account $account): void
    {
        $account->changePassword(null, $this->clock->now());
        $this->accounts->save($account);
        $this->journal->record(IdentityEventType::PasswordChanged, $account->id(), ['removed' => true]);
    }

    /**
     * Counts a failed sign-in with the password, and locks the account past the threshold.
     */
    public function recordFailedSignIn(Account $account): void
    {
        $now = $this->clock->now();
        $counted = null;
        $lockout = $this->lockout;
        $this->accounts->apply($account, static function (Account $stored) use ($now, $lockout, &$counted): bool {
            $wasLocked = $stored->isLocked($now);
            $stored->recordFailedSignIn($now, $lockout);
            $counted = [$stored->failedSignIns(), !$wasLocked && $stored->isLocked($now) ? $stored->lockedUntil() : null];

            return true;
        });

        if (null === $counted) {
            return;
        }

        [$failures, $lockedUntil] = $counted;
        $this->journal->record(IdentityEventType::SignInFailed, $account->id(), ['failures' => $failures]);

        if ($lockedUntil instanceof DateTimeImmutable) {
            $this->journal->record(IdentityEventType::AccountLocked, $account->id(), ['until' => $lockedUntil->getTimestamp()]);
        }
    }

    public function recordSignIn(Account $account): void
    {
        if (0 === $account->failedSignIns() && !$account->lockedUntil() instanceof DateTimeImmutable) {
            return;
        }

        $account->recordSignIn();
        $this->accounts->save($account);
    }

    public function save(Account $account): void
    {
        $this->accounts->save($account);
    }
}
