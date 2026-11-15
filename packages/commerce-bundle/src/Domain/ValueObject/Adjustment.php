<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * An amount added to or taken from an order, on one line or on the whole order, with the label a receipt shows.
 *
 * A **neutral** adjustment is shown but not added to the total: a tax already included in the price, for
 * instance. Amounts are signed: a discount is negative.
 *
 * Its **origin** is what gave it, as the processor that computed it names it — `promotion:black-friday` —, kept with
 * the order when it is placed: a module reads afterwards what the order got, rather than computing it again from
 * rules that may have changed since. It is never shown.
 */
final readonly class Adjustment
{
    private function __construct(
        public AdjustmentType $type,
        public string $label,
        public Money $amount,
        public ?SellableReference $line,
        public bool $neutral,
        public ?string $origin,
    ) {
    }

    /**
     * @param string|null $origin what gave it, 1 to 255 visible ASCII characters, as its processor names it
     */
    public static function of(AdjustmentType $type, string $label, Money $amount, ?SellableReference $line = null, bool $neutral = false, ?string $origin = null): self
    {
        if ('' === trim($label)) {
            throw InvalidValue::because(\sprintf('A %s adjustment has a label, which the receipt shows.', $type->value));
        }

        if (null !== $origin && 1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $origin)) {
            throw InvalidValue::because(\sprintf('"%s" is not the origin of an adjustment: 1 to 255 visible ASCII characters.', $origin));
        }

        return new self($type, $label, $amount, $line, $neutral, $origin);
    }
}
