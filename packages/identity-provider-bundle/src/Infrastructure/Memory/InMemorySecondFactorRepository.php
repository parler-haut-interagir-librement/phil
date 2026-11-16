<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The authenticator applications and recovery codes in memory, for tests; emptied between requests of a worker.
 */
final class InMemorySecondFactorRepository implements SecondFactorRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, TotpCredential> */
    private array $totp = [];

    /** @var array<string, RecoveryCodeSet> */
    private array $codes = [];

    #[Override]
    public function totp(string $accountId): ?TotpCredential
    {
        return self::copy($this->totp[$accountId] ?? null);
    }

    #[Override]
    public function recoveryCodes(string $accountId): ?RecoveryCodeSet
    {
        return self::copy($this->codes[$accountId] ?? null);
    }

    #[Override]
    public function save(TotpCredential|RecoveryCodeSet ...$factors): void
    {
        foreach ($factors as $factor) {
            if ($factor instanceof TotpCredential) {
                $this->totp[$factor->accountId()] = clone $factor;
            } else {
                $this->codes[$factor->accountId()] = clone $factor;
            }
        }
    }

    #[Override]
    public function remove(TotpCredential|RecoveryCodeSet $factor): void
    {
        if ($factor instanceof TotpCredential) {
            unset($this->totp[$factor->accountId()]);
        } else {
            unset($this->codes[$factor->accountId()]);
        }
    }

    /**
     * @template T of TotpCredential|RecoveryCodeSet
     *
     * @param T                $factor
     * @param Closure(T): bool $change
     */
    #[Override]
    public function apply(TotpCredential|RecoveryCodeSet $factor, Closure $change): bool
    {
        /** @var T|null $stored the same class as the factor */
        $stored = $factor instanceof TotpCredential ? $this->totp($factor->accountId()) : $this->recoveryCodes($factor->accountId());

        if (null === $stored || !$change($stored)) {
            return false;
        }

        $this->save($stored);

        return true;
    }

    #[Override]
    public function reset(): void
    {
        $this->totp = [];
        $this->codes = [];
    }
}
