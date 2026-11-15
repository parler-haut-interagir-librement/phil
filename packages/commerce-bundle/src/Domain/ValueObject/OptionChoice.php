<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One choice of a choice or ink option: the code a line's configuration holds, the label a receipt shows, the image
 * a storefront shows beside it, and what it adds to the unit price.
 */
final readonly class OptionChoice
{
    private function __construct(
        public string $code,
        public string $label,
        public ?string $image,
        public ?Money $supplement,
    ) {
    }

    /**
     * @param string     $code       1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit
     * @param string     $label      what the buyer and the receipt read
     * @param string     $image      the path or URL of its picture, 1 to 2048 visible ASCII characters
     * @param Money|null $supplement added to the unit price when chosen; never negative
     */
    public static function of(string $code, string $label, ?string $image = null, ?Money $supplement = null): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".', $code));
        }

        if ('' === trim($label)) {
            throw InvalidValue::because(\sprintf('The choice %s has no label.', $code));
        }

        if (null !== $image && 1 !== preg_match('/^[\x21-\x7E]{1,2048}$/D', $image)) {
            throw InvalidValue::because(\sprintf('The image of the choice %s is not a path or URL: 1 to 2048 visible ASCII characters.', $code));
        }

        if ($supplement instanceof Money && $supplement->isNegative()) {
            throw InvalidValue::because(\sprintf('The choice %s has a negative supplement; a discount is a promotion.', $code));
        }

        return new self($code, $label, $image, $supplement);
    }
}
