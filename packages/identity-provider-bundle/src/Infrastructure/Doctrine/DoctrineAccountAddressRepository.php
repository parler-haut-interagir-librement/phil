<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Contract\AccountAddressRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;

/**
 * The addresses in `phil_identity_address`; each save is flushed.
 */
final readonly class DoctrineAccountAddressRepository implements AccountAddressRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forAccount(string $accountId): array
    {
        /** @var list<AccountAddress> */
        return $this->entityManager->getRepository(AccountAddress::class)->findBy(['accountId' => $accountId], ['purpose' => 'ASC']);
    }

    #[Override]
    public function findByCanonical(AddressPurpose $purpose, string $canonical): ?AccountAddress
    {
        return $this->entityManager->getRepository(AccountAddress::class)->findOneBy(['purpose' => $purpose, 'canonical' => $canonical]);
    }

    #[Override]
    public function save(AccountAddress ...$addresses): void
    {
        foreach ($addresses as $address) {
            $this->entityManager->persist($address);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(AccountAddress $address): void
    {
        $this->entityManager->remove($address);
        $this->entityManager->flush();
    }
}
