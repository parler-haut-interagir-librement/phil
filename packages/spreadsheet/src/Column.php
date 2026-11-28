<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * A column of a sheet: the code a program reads, the label a person reads, and a width in characters.
 */
final readonly class Column
{
    /**
     * @param string      $code  the machine name of the column, such as `q1` or `postal_code`
     * @param string|null $label the heading a person reads; the code when null
     * @param float|null  $width the width in characters, between 1 and 255; the spreadsheet's own when null
     *
     * @throws InvalidSpreadsheet when the width is out of its bounds
     */
    public function __construct(
        public string $code,
        public ?string $label = null,
        public ?float $width = null,
    ) {
        if (null !== $width && ($width < 1 || $width > 255)) {
            throw InvalidSpreadsheet::because(\sprintf('The width of the column "%s" lies between 1 and 255 characters.', $code));
        }
    }

    /**
     * The heading of the column for the headings asked: its code, or its label.
     */
    public function heading(Headings $headings): string
    {
        return Headings::Codes === $headings ? $this->code : ($this->label ?? $this->code);
    }
}
