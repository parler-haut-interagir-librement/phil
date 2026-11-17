<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The accounts in `phil_identity_account`; each save is flushed, and a change applied under a lock of its row.
 */
final readonly class DoctrineAccountRepository implements AccountRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Account
    {
        return $this->entityManager->find(Account::class, $id);
    }

    #[Override]
    public function findByEmail(string $email): ?Account
    {
        return $this->entityManager->getRepository(Account::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    #[Override]
    public function save(Account ...$accounts): void
    {
        foreach ($accounts as $account) {
            $this->entityManager->persist($account);
        }

        $this->entityManager->flush();
    }

    /**
     * @param Closure(Account): bool $change
     */
    #[Override]
    public function apply(Account $account, Closure $change): bool
    {
        return $this->entityManager->getConnection()->transactional(function () use ($account, $change): bool {
            /** @var Account|null $current */
            $current = $this->entityManager->createQuery(\sprintf('SELECT a FROM %s a WHERE a.id = :id', Account::class))
                ->setParameter('id', $account->id())
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
    public function remove(Account $account): void
    {
        $this->entityManager->remove($account);
        $this->entityManager->flush();
    }
}
