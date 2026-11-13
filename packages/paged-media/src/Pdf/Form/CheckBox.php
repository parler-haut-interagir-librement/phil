<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Phil\PagedMedia\Pdf\Color;

/**
 * A square a reader checks: a feat taken, a consent given, an option ordered. When it is checked, the field's value is
 * its export value; otherwise it is `Off`.
 */
final readonly class CheckBox
{
    /**
     * @param string     $name        the field's name, unique in the document, without a period
     * @param float      $size        the side of the square, in points
     * @param string     $exportValue the value the field takes when it is checked
     * @param Color|null $color       the check mark's colour; black when none is given
     * @param Color|null $border      the square's outline, none when none is given
     * @param Color|null $background  the square's fill, none when none is given
     */
    public function __construct(
        public string $name,
        public float $x,
        public float $y,
        public float $size = 12,
        public bool $checked = false,
        public string $exportValue = 'Yes',
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        public ?Color $color = null,
        public ?Color $border = null,
        public ?Color $background = null,
    ) {
        Check::name($name);
        Check::measure($size, 'size');
        Check::exportValue($exportValue);
    }
}
