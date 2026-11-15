<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;

/**
 * The authenticator applications in `phil_identity_totp` and the recovery codes in `phil_identity_recovery_codes`;
 * each save is flushed.
 */
final readonly class DoctrineSecondFactorRepository implements SecondFactorRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function totp(string $accountId): ?TotpCredential
    {
        return $this->entityManager->find(TotpCredential::class, $accountId);
    }

    #[Override]
    public function recoveryCodes(string $accountId): ?RecoveryCodeSet
    {
        return $this->entityManager->find(RecoveryCodeSet::class, $accountId);
    }

    #[Override]
    public function save(TotpCredential|RecoveryCodeSet ...$factors): void
    {
        foreach ($factors as $factor) {
            $this->entityManager->persist($factor);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(TotpCredential|RecoveryCodeSet $factor): void
    {
        $this->entityManager->remove($factor);
        $this->entityManager->flush();
    }
}
