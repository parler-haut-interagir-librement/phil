<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The accounts in `phil_identity_account`; each save is flushed.
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

    #[Override]
    public function remove(Account $account): void
    {
        $this->entityManager->remove($account);
        $this->entityManager->flush();
    }
}
