<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;

/**
 * A text box a reader fills: a name on a character sheet, a line of an order form, notes on several lines. Its box
 * starts at its bottom left corner, in points from the bottom left corner of the page.
 */
final readonly class TextField
{
    /**
     * @param string      $name       the field's name, unique in the document, without a period
     * @param string      $value      the text the field holds when the document opens
     * @param bool        $multiline  whether the text wraps and may hold line breaks
     * @param int|null    $maxLength  the most characters a reader may type
     * @param string|null $tooltip    what a reader's assistive technology says of the field
     * @param Color|null  $color      the text's colour; black when none is given
     * @param Color|null  $border     the box's outline, none when none is given
     * @param Color|null  $background the box's fill, none when none is given
     */
    public function __construct(
        public string $name,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $value = '',
        public float $fontSize = 10,
        public StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        public bool $multiline = false,
        public ?int $maxLength = null,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        public ?Color $color = null,
        public ?Color $border = null,
        public ?Color $background = null,
    ) {
        Check::name($name);
        Check::measure($width, 'width');
        Check::measure($height, 'height');
        Check::measure($fontSize, 'font size');
        Check::text($value, $multiline, 'text field value');

        if (null !== $maxLength && ($maxLength < 1 || mb_strlen($value) > $maxLength)) {
            throw InvalidDocument::because(\sprintf('The most characters of the field "%s" is at least 1 and at least the length of its value; %d is not.', $name, $maxLength));
        }
    }
}
