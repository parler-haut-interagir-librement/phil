<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One line of an order: what is sold, how many, at what unit price, and with which option values. The unit price is
 * the one the price resolvers gave when the line was last priced, which may differ from the sellable's base price;
 * what the options add is an adjustment of the line, never part of its unit price.
 *
 * A configured line's reference carries its configuration's fingerprint: the same sellable with other values is
 * another line.
 */
final readonly class OrderLine
{
    private function __construct(
        public SellableReference $reference,
        public string $description,
        public int $quantity,
        public Money $unitPrice,
        public string $taxCategory,
        public LineConfiguration $configuration,
    ) {
    }

    /**
     * A line of the sellable, its configuration checked by the sellable's options: a sellable with required or draw
     * options takes a configuration even when none is given.
     *
     * @throws InvalidConfiguration when the sellable's options refuse the configuration
     */
    public static function of(Sellable $sellable, int $quantity, Money $unitPrice, ?LineConfiguration $configuration = null): self
    {
        return self::stored($sellable, $quantity, $unitPrice, $sellable->options->check($configuration ?? LineConfiguration::none()));
    }

    /**
     * A line as it was stored, its configuration kept as it was sold rather than checked again against options that
     * may have changed since: what a storage adapter rebuilds.
     */
    public static function stored(Sellable $sellable, int $quantity, Money $unitPrice, LineConfiguration $configuration): self
    {
        self::checkQuantity($quantity);
        self::checkUnitPrice($sellable->reference, $unitPrice);

        return new self($sellable->reference->configuredBy($configuration), $sellable->description, $quantity, $unitPrice, $sellable->taxCategory, $configuration);
    }

    public function withQuantity(int $quantity): self
    {
        self::checkQuantity($quantity);

        foreach ($this->configuration->values as $value) {
            if ([] !== $value->draws) {
                throw InvalidValue::because(\sprintf('The line %s was drawn for %d units; its quantity no longer changes.', $this->reference->value(), $this->quantity));
            }
        }

        return new self($this->reference, $this->description, $quantity, $this->unitPrice, $this->taxCategory, $this->configuration);
    }

    public function withUnitPrice(Money $unitPrice): self
    {
        self::checkUnitPrice($this->reference, $unitPrice);

        return new self($this->reference, $this->description, $this->quantity, $unitPrice, $this->taxCategory, $this->configuration);
    }

    /**
     * The line with the draws of one of its options, one per unit.
     *
     * @param list<string> $draws
     */
    public function withDraws(string $option, array $draws): self
    {
        if (!\in_array($option, $this->configuration->pending(), true)) {
            throw InvalidValue::because(\sprintf('The line %s has no option %s still to draw.', $this->reference->value(), $option));
        }

        if (\count($draws) !== $this->quantity) {
            throw InvalidValue::because(\sprintf('The line %s holds %d units: its option %s takes %d draws, not %d.', $this->reference->value(), $this->quantity, $option, $this->quantity, \count($draws)));
        }

        return new self($this->reference, $this->description, $this->quantity, $this->unitPrice, $this->taxCategory, $this->configuration->withDraws($option, $draws));
    }

    /**
     * The unit price times the quantity, before any adjustment.
     */
    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    private static function checkQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw InvalidValue::because(\sprintf('A line holds at least one unit; %d is not. Remove the line instead.', $quantity));
        }
    }

    private static function checkUnitPrice(SellableReference $reference, Money $unitPrice): void
    {
        if ($unitPrice->isNegative()) {
            throw InvalidValue::because(\sprintf('The line %s has a negative unit price; a discount is an adjustment.', $reference->value()));
        }
    }
}
