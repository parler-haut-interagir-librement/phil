<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout;

use InvalidArgumentException;

/**
 * The size of a braille page and what is written on it besides the text.
 *
 * The default, 40 cells by 25 lines, is the page of an embosser on 11 by 11.5 inch paper.
 */
final readonly class PageFormat
{
    public const int MIN_CELLS = 12;

    public const int MAX_CELLS = 200;

    public const int MIN_LINES = 3;

    public const int MAX_LINES = 200;

    /**
     * @param int      $cellsPerLine       the cells of a line
     * @param int      $linesPerPage       the lines of a page
     * @param bool     $braillePageNumbers whether the braille page number ends the last line of each page
     * @param bool     $printPageNumbers   whether the print page ends the first line of each page, and its change within a
     *                                     page is shown by a line; it needs the document's {@see \Phil\BrailleExport\Document\PrintPage} markers
     * @param int|null $pagesPerVolume     the pages of a volume, the book split into volumes of that many; one volume without it
     * @param bool     $duplex             whether the pages are embossed on both sides, as a PEF file says
     * @param int      $maxPages           the most pages a document may fill, a bound on the work of one export
     */
    public function __construct(
        public int $cellsPerLine = 40,
        public int $linesPerPage = 25,
        public bool $braillePageNumbers = true,
        public bool $printPageNumbers = true,
        public ?int $pagesPerVolume = null,
        public bool $duplex = false,
        public int $maxPages = 10_000,
    ) {
        if ($cellsPerLine < self::MIN_CELLS || $cellsPerLine > self::MAX_CELLS) {
            throw new InvalidArgumentException(\sprintf('A line holds from %d to %d cells.', self::MIN_CELLS, self::MAX_CELLS));
        }

        if ($linesPerPage < self::MIN_LINES || $linesPerPage > self::MAX_LINES) {
            throw new InvalidArgumentException(\sprintf('A page holds from %d to %d lines.', self::MIN_LINES, self::MAX_LINES));
        }

        if (null !== $pagesPerVolume && $pagesPerVolume < 1) {
            throw new InvalidArgumentException('A volume holds at least one page.');
        }

        if ($maxPages < 1) {
            throw new InvalidArgumentException('A document may fill at least one page.');
        }
    }
}
