<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\AddressCanonicalizer;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Contract\AccountAddressRepository;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;
use Psr\Clock\ClockInterface;

/**
 * The addresses of an account by purpose: the sign-in address is the account's own, changed by `Accounts`; a recovery
 * address and a notice address are rows of their own, each with a canonical form that no other account holds for the
 * same purpose.
 *
 * A new or changed address is unverified until the application verifies it — after a link it sent there, for
 * instance —, and `addressFor()` gives a verified address only, the sign-in address otherwise: a message never goes to
 * an address nobody proved they read.
 */
final readonly class AccountAddresses
{
    public function __construct(
        private AccountAddressRepository $addresses,
        private Journal $journal,
        private ClockInterface $clock,
        private AddressCanonicalizer $canonicalizer = new EmailCanonicalizer(),
    ) {
    }

    /**
     * @return list<AccountAddress> the addresses of the account other than the sign-in one
     */
    public function of(Account $account): array
    {
        return $this->addresses->forAccount($account->id());
    }

    public function get(Account $account, AddressPurpose $purpose): ?AccountAddress
    {
        return array_find($this->of($account), static fn (AccountAddress $address): bool => $address->purpose() === $purpose);
    }

    /**
     * Where to write to the account for a purpose: its verified address of that purpose, else its sign-in address.
     */
    public function addressFor(Account $account, AddressPurpose $purpose): string
    {
        $address = $this->get($account, $purpose);

        return $address instanceof AccountAddress && $address->isVerified() ? $address->address() : $account->email();
    }

    /**
     * Sets the account's address of a purpose, a new one or in place of the one it had.
     *
     * @throws Refused when the purpose is signing in, the address is out of shape, or another account holds it for
     *                 the purpose
     */
    public function set(Account $account, AddressPurpose $purpose, string $address): AccountAddress
    {
        if (AddressPurpose::SignIn === $purpose) {
            throw Refused::because('address.sign_in');
        }

        try {
            $normalized = Account::normalizeEmail($address);
        } catch (InvalidValue) {
            throw Refused::because('email.invalid');
        }

        $canonical = $this->canonicalizer->canonical($normalized);
        $holder = $this->addresses->findByCanonical($purpose, $canonical);

        if ($holder instanceof AccountAddress && $holder->accountId() !== $account->id()) {
            throw Refused::because('email.taken');
        }

        $current = $this->get($account, $purpose);

        if ($current instanceof AccountAddress) {
            $current->change($normalized, $canonical);
        } else {
            $current = AccountAddress::add($account->id(), $purpose, $normalized, $canonical, $this->clock->now());
        }

        $this->addresses->save($current);
        $this->journal->record(IdentityEventType::AddressSet, $account->id(), ['purpose' => $purpose->value]);

        return $current;
    }

    /**
     * Marks the account's address of a purpose as verified.
     *
     * @return bool false when the account has no address of the purpose
     */
    public function verify(Account $account, AddressPurpose $purpose): bool
    {
        $address = $this->get($account, $purpose);

        if (!$address instanceof AccountAddress) {
            return false;
        }

        if (!$address->isVerified()) {
            $address->verify($this->clock->now());
            $this->addresses->save($address);
            $this->journal->record(IdentityEventType::AddressVerified, $account->id(), ['purpose' => $purpose->value]);
        }

        return true;
    }

    public function remove(Account $account, AddressPurpose $purpose): void
    {
        $address = $this->get($account, $purpose);

        if ($address instanceof AccountAddress) {
            $this->addresses->remove($address);
            $this->journal->record(IdentityEventType::AddressRemoved, $account->id(), ['purpose' => $purpose->value]);
        }
    }
}
