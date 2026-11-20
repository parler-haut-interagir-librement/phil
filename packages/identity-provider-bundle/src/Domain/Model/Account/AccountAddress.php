<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Account;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;

/**
 * An address of an account for a purpose other than signing in, as a row of `phil_identity_address`: the address as
 * normalized, its canonical form, and when it was verified. An account has at most one of each purpose, and a
 * canonical form belongs to one account for each purpose: two spellings of one mailbox are one address.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_identity_address_canonical', columns: ['purpose', 'canonical'])]
#[ORM\UniqueConstraint(name: 'phil_identity_address_account', columns: ['account_id', 'purpose'])]
final class AccountAddress
{
    public const string TABLE = 'phil_identity_address';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: Account::ID_MAX_LENGTH)]
        private readonly string $accountId,
        #[ORM\Column(name: 'purpose', length: AddressPurpose::MAX_LENGTH, enumType: AddressPurpose::class)]
        private readonly AddressPurpose $purpose,
        #[ORM\Column(name: 'address', length: Account::EMAIL_MAX_LENGTH)]
        private string $address,
        #[ORM\Column(name: 'canonical', length: Account::EMAIL_MAX_LENGTH)]
        private string $canonical,
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'verified_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $verifiedAt = null,
    ) {
    }

    /**
     * @param string $canonical the canonical form of the address, as `AddressCanonicalizer` gives it
     *
     * @throws InvalidValue when the purpose is signing in, or the address is out of shape
     */
    public static function add(string $accountId, AddressPurpose $purpose, string $address, string $canonical, DateTimeImmutable $now): self
    {
        if (AddressPurpose::SignIn === $purpose) {
            throw InvalidValue::because('The sign-in address is the address of the account itself.');
        }

        return new self(self::idOf($accountId, $purpose), $accountId, $purpose, Account::normalizeEmail($address), $canonical, $now);
    }

    /**
     * The key of the row of an account's address of a purpose.
     */
    public static function idOf(string $accountId, AddressPurpose $purpose): string
    {
        return hash('sha256', $purpose->value.' '.$accountId);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function purpose(): AddressPurpose
    {
        return $this->purpose;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function canonical(): string
    {
        return $this->canonical;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt instanceof DateTimeImmutable;
    }

    public function verify(DateTimeImmutable $now): void
    {
        $this->verifiedAt ??= $now;
    }

    /**
     * Another address for the same purpose, unverified until it is verified in turn.
     *
     * @throws InvalidValue when the address is out of shape
     */
    public function change(string $address, string $canonical): void
    {
        $address = Account::normalizeEmail($address);

        if ($address !== $this->address) {
            $this->address = $address;
            $this->verifiedAt = null;
        }

        $this->canonical = $canonical;
    }
}
