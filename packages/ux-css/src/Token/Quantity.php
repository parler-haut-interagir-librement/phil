<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

/**
 * A number and its CSS unit, lower case; `''` for a plain number.
 */
final readonly class Quantity
{
    public function __construct(
        public float $value,
        public string $unit = '',
    ) {
    }

    public function isNumber(): bool
    {
        return '' === $this->unit;
    }
}
