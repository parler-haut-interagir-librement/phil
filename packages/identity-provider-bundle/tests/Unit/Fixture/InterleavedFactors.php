<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;

/**
 * The second factors in memory, where a request runs between the read of a row and what follows it, as a second
 * worker would, for the tests of concurrent use.
 */
final class InterleavedFactors implements SecondFactorRepository
{
    /**
     * Runs once, right after the next read of an application or of recovery codes, before the caller sees the row.
     */
    public ?Closure $meanwhile = null;

    public function __construct(public readonly InMemorySecondFactorRepository $inner = new InMemorySecondFactorRepository())
    {
    }

    #[Override]
    public function totp(string $accountId): ?TotpCredential
    {
        return $this->interleave($this->inner->totp($accountId));
    }

    #[Override]
    public function recoveryCodes(string $accountId): ?RecoveryCodeSet
    {
        return $this->interleave($this->inner->recoveryCodes($accountId));
    }

    #[Override]
    public function save(TotpCredential|RecoveryCodeSet ...$factors): void
    {
        $this->inner->save(...$factors);
    }

    #[Override]
    public function remove(TotpCredential|RecoveryCodeSet $factor): void
    {
        $this->inner->remove($factor);
    }

    #[Override]
    public function apply(TotpCredential|RecoveryCodeSet $factor, Closure $change): bool
    {
        return $this->inner->apply($factor, $change);
    }

    /**
     * @template T
     *
     * @param T $read
     *
     * @return T
     */
    private function interleave(mixed $read): mixed
    {
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $read;
    }
}
