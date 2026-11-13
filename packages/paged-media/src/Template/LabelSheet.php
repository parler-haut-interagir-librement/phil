<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * A sheet of labels or cards in a grid: the page, how many columns and rows, each label's size, the margins from the
 * top left corner of the page and the gaps between labels, in points. The named sheets are common stock sizes; any
 * other is built with `of()`.
 */
final readonly class LabelSheet
{
    private const float INCH = 72;

    private function __construct(
        public PageSize $page,
        public int $columns,
        public int $rows,
        public float $labelWidth,
        public float $labelHeight,
        public float $top,
        public float $left,
        public float $columnGap,
        public float $rowGap,
    ) {
    }

    public static function of(PageSize $page, int $columns, int $rows, float $labelWidth, float $labelHeight, float $top = 0, float $left = 0, float $columnGap = 0, float $rowGap = 0): self
    {
        if ($columns < 1 || $rows < 1 || $labelWidth <= 0 || $labelHeight <= 0 || $top < 0 || $left < 0 || $columnGap < 0 || $rowGap < 0) {
            throw InvalidDocument::because('A label sheet has at least one column and one row, labels of a size above zero, and margins and gaps of zero or more.');
        }

        $width = $left + $columns * $labelWidth + ($columns - 1) * $columnGap;
        $height = $top + $rows * $labelHeight + ($rows - 1) * $rowGap;

        if ($width > $page->width + 0.01 || $height > $page->height + 0.01) {
            throw InvalidDocument::because(\sprintf('The labels of a sheet fit on its page: they need %s by %s points, the page is %s by %s.', round($width, 2), round($height, 2), $page->width, $page->height));
        }

        return new self($page, $columns, $rows, $labelWidth, $labelHeight, $top, $left, $columnGap, $rowGap);
    }

    /**
     * One label filling a page of the size given: a card printed on card stock of its own size.
     */
    public static function single(PageSize $page): self
    {
        return self::of($page, 1, 1, $page->width, $page->height);
    }

    /**
     * Thirty address labels of 2⅝ by 1 inch on a Letter page, three columns of ten.
     */
    public static function letter30(): self
    {
        return self::of(PageSize::letter(), 3, 10, 2.625 * self::INCH, self::INCH, 0.5 * self::INCH, 0.1875 * self::INCH, 0.125 * self::INCH);
    }

    /**
     * Ten cards of 3½ by 2 inches on a Letter page, two columns of five: business-card stock.
     */
    public static function letter10(): self
    {
        return self::of(PageSize::letter(), 2, 5, 3.5 * self::INCH, 2 * self::INCH, 0.5 * self::INCH, 0.75 * self::INCH);
    }

    /**
     * Six name badges of 4 by 3 inches on a Letter page, two columns of three.
     */
    public static function letter6(): self
    {
        return self::of(PageSize::letter(), 2, 3, 4 * self::INCH, 3 * self::INCH, self::INCH, 0.25 * self::INCH);
    }

    /**
     * Twenty-one labels of 70 by 42.3 millimetres on an A4 page, three columns of seven, without margins.
     */
    public static function a4x21(): self
    {
        $page = PageSize::a4();

        return self::of($page, 3, 7, $page->width / 3, round(42.3 * self::INCH / 25.4, 2), round(($page->height - 7 * round(42.3 * self::INCH / 25.4, 2)) / 2, 2));
    }

    /**
     * Eight cards of 105 by 74 millimetres on an A4 page, two columns of four, without margins.
     */
    public static function a4x8(): self
    {
        $page = PageSize::a4();

        return self::of($page, 2, 4, $page->width / 2, $page->height / 4);
    }

    public function perPage(): int
    {
        return $this->columns * $this->rows;
    }

    /**
     * The bottom left corner of the label at a place on the page, counted from the top left label, row by row.
     *
     * @return array{float, float}
     */
    public function corner(int $place): array
    {
        $column = $place % $this->columns;
        $row = intdiv($place, $this->columns);

        return [
            $this->left + $column * ($this->labelWidth + $this->columnGap),
            $this->page->height - $this->top - ($row + 1) * $this->labelHeight - $row * $this->rowGap,
        ];
    }
}
