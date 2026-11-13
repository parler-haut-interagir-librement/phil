<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

/**
 * One choice of a `RadioGroup`: the value the group takes when it is chosen, and where its circle is drawn, from the
 * bottom left corner of the square that holds it.
 */
final readonly class RadioButton
{
    public function __construct(
        public string $value,
        public float $x,
        public float $y,
        public float $size = 12,
    ) {
        Check::exportValue($value);
        Check::measure($size, 'size');
    }
}
