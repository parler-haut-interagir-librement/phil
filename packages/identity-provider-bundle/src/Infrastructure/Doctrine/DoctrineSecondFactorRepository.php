<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;

/**
 * The authenticator applications in `phil_identity_totp` and the recovery codes in `phil_identity_recovery_codes`;
 * each save is flushed. A change applied reads its row again with `SELECT … FOR UPDATE`, in a transaction, so that
 * a second request waits for the first to commit and then sees its write.
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

    /**
     * @template T of TotpCredential|RecoveryCodeSet
     *
     * @param T                $factor
     * @param Closure(T): bool $change
     */
    #[Override]
    public function apply(TotpCredential|RecoveryCodeSet $factor, Closure $change): bool
    {
        return $this->entityManager->getConnection()->transactional(function () use ($factor, $change): bool {
            /** @var T|null $current the same class as the factor, or nothing */
            $current = $this->entityManager->createQuery(\sprintf('SELECT f FROM %s f WHERE f.accountId = :account', $factor::class))
                ->setParameter('account', $factor->accountId())
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->setHint(Query::HINT_REFRESH, true)
                ->getOneOrNullResult();

            if (null === $current || !$change($current)) {
                return false;
            }

            $this->entityManager->flush();

            return true;
        });
    }

    #[Override]
    public function remove(TotpCredential|RecoveryCodeSet $factor): void
    {
        $this->entityManager->remove($factor);
        $this->entityManager->flush();
    }
}
