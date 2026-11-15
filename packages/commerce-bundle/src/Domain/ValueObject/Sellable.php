<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * What a module tells the core about something it sells: the line's description, its base unit price before any
 * price list, the tax category the `TaxRateResolver` reads, and the options a line of it may or must set, which check
 * the line's configuration.
 */
final readonly class Sellable
{
    private function __construct(
        public SellableReference $reference,
        public string $description,
        public Money $unitPrice,
        public string $taxCategory,
        public OptionSet $options,
    ) {
    }

    public static function of(SellableReference $reference, string $description, Money $unitPrice, string $taxCategory, ?OptionSet $options = null): self
    {
        if (null !== $reference->configuration) {
            throw InvalidValue::because(\sprintf('The sellable %s is named without the configuration of a line.', $reference->value()));
        }

        if ('' === trim($description)) {
            throw InvalidValue::because(\sprintf('The sellable %s has no description.', $reference->value()));
        }

        if ($unitPrice->isNegative()) {
            throw InvalidValue::because(\sprintf('The sellable %s has a negative unit price; a discount is an adjustment.', $reference->value()));
        }

        if ('' === $taxCategory) {
            throw InvalidValue::because(\sprintf('The sellable %s has no tax category.', $reference->value()));
        }

        return new self($reference, $description, $unitPrice, $taxCategory, $options ?? OptionSet::none());
    }

    /**
     * The sellable with the options its catalogue declares.
     */
    public function withOptions(OptionSet $options): self
    {
        return new self($this->reference, $this->description, $this->unitPrice, $this->taxCategory, $options);
    }
}
