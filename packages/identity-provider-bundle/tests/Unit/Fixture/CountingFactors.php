<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;

/**
 * The second factors in memory, listing the operations in order, for the tests.
 */
final class CountingFactors implements SecondFactorRepository
{
    /** @var list<string> */
    public array $operations = [];

    public function __construct(public readonly InMemorySecondFactorRepository $inner = new InMemorySecondFactorRepository())
    {
    }

    #[Override]
    public function totp(string $accountId): ?TotpCredential
    {
        return $this->inner->totp($accountId);
    }

    #[Override]
    public function recoveryCodes(string $accountId): ?RecoveryCodeSet
    {
        return $this->inner->recoveryCodes($accountId);
    }

    #[Override]
    public function save(TotpCredential|RecoveryCodeSet ...$factors): void
    {
        $this->operations[] = 'save';
        $this->inner->save(...$factors);
    }

    #[Override]
    public function remove(TotpCredential|RecoveryCodeSet $factor): void
    {
        $this->operations[] = 'remove';
        $this->inner->remove($factor);
    }
}
