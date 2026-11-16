<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The authenticator application and the recovery codes of an account.
 *
 * Enrolment keeps a sealed secret that counts for nothing until a first code confirms it; a code is accepted once,
 * within one period before or after the current one — once even when two requests present it at the same time, the
 * counter and the recovery codes being read again and held while they change. Recovery codes are generated when the application is confirmed,
 * shown once, and each one replaces a code of the application one time.
 */
final readonly class SecondFactors
{
    public function __construct(
        private SecondFactorRepository $factors,
        private SecretCipher $cipher,
        private RecoveryCodes $recoveryCodes,
        private Journal $journal,
        private ClockInterface $clock,
        private string $issuer,
        private int $window = 1,
    ) {
    }

    /**
     * A new secret for the account, replacing one not yet confirmed; refused when one is confirmed.
     *
     * @return array{secret: string, uri: string} the base32 secret and the `otpauth://` address of its QR code
     *
     * @throws Refused when the account already has a confirmed application
     */
    public function enroll(Account $account): array
    {
        $existing = $this->factors->totp($account->id());

        if (true === $existing?->isConfirmed()) {
            throw Refused::because('totp.already_enabled');
        }

        if ($existing instanceof TotpCredential) {
            $this->factors->remove($existing);
        }

        $totp = new Totp(Totp::generateSecret(), $this->window);
        $this->factors->save(TotpCredential::enroll($account->id(), $this->cipher->seal($totp->base32Secret(), $this->context($account)), $this->clock->now()));

        return ['secret' => $totp->base32Secret(), 'uri' => $totp->provisioningUri($this->issuer, $account->email())];
    }

    /**
     * Confirms the application with its first code.
     *
     * @return list<string> the recovery codes to show once, when the module is on
     *
     * @throws Refused when there is no pending application or the code does not match
     */
    public function confirm(Account $account, #[SensitiveParameter] string $code, bool $withRecoveryCodes = true): array
    {
        $credential = $this->factors->totp($account->id());

        if (!$credential instanceof TotpCredential || $credential->isConfirmed()) {
            throw Refused::because('totp.not_pending');
        }

        if (!$this->accept($account, $credential, $code)) {
            throw Refused::because('totp.invalid');
        }

        $this->journal->record(IdentityEventType::TotpEnabled, $account->id());

        return $withRecoveryCodes ? $this->regenerateRecoveryCodes($account) : [];
    }

    public function hasTotp(Account $account): bool
    {
        return true === $this->factors->totp($account->id())?->isConfirmed();
    }

    /**
     * Whether the code of the confirmed application matches, once.
     */
    public function verifyTotp(Account $account, #[SensitiveParameter] string $code): bool
    {
        $credential = $this->factors->totp($account->id());

        return $credential instanceof TotpCredential && $credential->isConfirmed() && $this->accept($account, $credential, $code);
    }

    public function disableTotp(Account $account): void
    {
        $credential = $this->factors->totp($account->id());

        if ($credential instanceof TotpCredential) {
            $this->factors->remove($credential);
            $this->journal->record(IdentityEventType::TotpDisabled, $account->id());
        }

        $codes = $this->factors->recoveryCodes($account->id());

        if ($codes instanceof RecoveryCodeSet) {
            $this->factors->remove($codes);
        }
    }

    /**
     * New recovery codes, replacing the old ones.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Account $account): array
    {
        $codes = $this->recoveryCodes->generate();
        $hashes = array_map($this->recoveryCodes->hash(...), $codes);
        $set = $this->factors->recoveryCodes($account->id());
        $now = $this->clock->now();

        if (!$set instanceof RecoveryCodeSet) {
            $set = RecoveryCodeSet::generate($account->id(), $hashes, $now);
        } else {
            $set->replace($hashes, $now);
        }

        $this->factors->save($set);
        $this->journal->record(IdentityEventType::RecoveryCodesGenerated, $account->id());

        return $codes;
    }

    public function remainingRecoveryCodes(Account $account): int
    {
        return $this->factors->recoveryCodes($account->id())?->remaining() ?? 0;
    }

    /**
     * Whether the code is one of the account's unused recovery codes; it is used up when it is.
     */
    public function useRecoveryCode(Account $account, #[SensitiveParameter] string $code): bool
    {
        $set = $this->factors->recoveryCodes($account->id());
        $position = $set instanceof RecoveryCodeSet ? $this->recoveryCodes->match($code, $set->hashes()) : null;

        if (!$set instanceof RecoveryCodeSet || null === $position) {
            return false;
        }

        $hash = $set->hashes()[$position];
        $remaining = null;
        $used = $this->factors->apply($set, static function (RecoveryCodeSet $current) use ($position, $hash, &$remaining): bool {
            if (($current->hashes()[$position] ?? null) !== $hash) {
                return false;
            }

            $current->use($position);
            $remaining = $current->remaining();

            return true;
        });

        if ($used) {
            $this->journal->record(IdentityEventType::RecoveryCodeUsed, $account->id(), ['remaining' => $remaining]);
        }

        return $used;
    }

    private function accept(Account $account, TotpCredential $credential, #[SensitiveParameter] string $code): bool
    {
        $totp = Totp::fromBase32($this->cipher->open($credential->storedSecret(), $this->context($account)), $this->window);
        $now = $this->clock->now();
        $counter = $totp->verify($code, $now->getTimestamp(), $credential->lastCounter());

        if (null === $counter) {
            return false;
        }

        $secret = $credential->storedSecret();

        return $this->factors->apply($credential, static function (TotpCredential $current) use ($secret, $counter, $now): bool {
            if ($current->storedSecret() !== $secret || (null !== $current->lastCounter() && $counter <= $current->lastCounter())) {
                return false;
            }

            $current->accept($counter, $now);

            return true;
        });
    }

    private function context(Account $account): string
    {
        return 'totp:'.$account->id();
    }
}
