<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;

/**
 * Who owns units of stock: the shop, which bought them, or a consignor, who left them to be sold for a share.
 *
 * A consignor is the application's identifier of a supplier, an author or an artisan — the supplier code of a purchase
 * order, a contact's identifier —, compared byte by byte. The ledger stores the shop as the empty consignor.
 */
final readonly class Owner
{
    private function __construct(
        public ?string $consignor,
    ) {
    }

    public static function shop(): self
    {
        return new self(null);
    }

    public static function consignor(string $consignor): self
    {
        return new self(InvalidValue::checkIdentifier($consignor, 'consignor'));
    }

    /**
     * Reads the column the ledger stores: the empty string for the shop.
     */
    public static function fromKey(string $key): self
    {
        return '' === $key ? self::shop() : self::consignor($key);
    }

    public function isConsigned(): bool
    {
        return null !== $this->consignor;
    }

    /**
     * What the ledger stores: the consignor, or the empty string for the shop.
     */
    public function key(): string
    {
        return $this->consignor ?? '';
    }

    public function equals(self $other): bool
    {
        return $this->consignor === $other->consignor;
    }

    public function describe(): string
    {
        return null === $this->consignor ? 'the shop' : \sprintf('the consignor %s', $this->consignor);
    }
}
