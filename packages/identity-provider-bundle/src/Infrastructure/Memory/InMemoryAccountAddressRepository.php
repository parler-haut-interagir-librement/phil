<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Override;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Contract\AccountAddressRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The addresses in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryAccountAddressRepository implements AccountAddressRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, AccountAddress> */
    private array $addresses = [];

    #[Override]
    public function forAccount(string $accountId): array
    {
        $addresses = array_values(array_filter($this->addresses, static fn (AccountAddress $address): bool => $address->accountId() === $accountId));
        usort($addresses, static fn (AccountAddress $a, AccountAddress $b): int => $a->purpose()->value <=> $b->purpose()->value);

        return self::copies($addresses);
    }

    #[Override]
    public function findByCanonical(AddressPurpose $purpose, string $canonical): ?AccountAddress
    {
        return self::copy(array_find($this->addresses, static fn (AccountAddress $address): bool => $address->purpose() === $purpose && $address->canonical() === $canonical));
    }

    #[Override]
    public function save(AccountAddress ...$addresses): void
    {
        foreach ($addresses as $address) {
            $this->addresses[$address->id()] = clone $address;
        }
    }

    #[Override]
    public function remove(AccountAddress $address): void
    {
        unset($this->addresses[$address->id()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->addresses = [];
    }
}
