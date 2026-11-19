<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Book\Page;
use Phil\BrailleExport\Book\Section;
use Phil\BrailleExport\Book\Volume;
use Phil\BrailleExport\Exception\DocumentTooLong;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Translation\Braille;

/**
 * The pages of one layout, filled a line at a time: the page numbers it writes, the room it leaves them, the sections
 * and the volumes. One instance serves one document.
 *
 * @internal
 */
final class PageSink
{
    /** The blank cells, at least, between the text of a line and the page number that ends it. */
    private const int NUMBER_GAP = 3;

    /** @var list<string> */
    private array $lines = [];

    /** @var list<Page> */
    private array $pages = [];

    /** @var list<int> the index of the first page of each section */
    private array $sections = [0];

    private int $number = 1;

    private ?string $printPage = null;

    private string $printCells = '';

    /** The print page at the top of the page being filled, and its cells. */
    private ?string $topPage = null;

    private string $topCells = '';

    public function __construct(
        private readonly PageFormat $format,
        private readonly PageNumbers $numbers,
    ) {
    }

    /**
     * The cells the next line may hold, the room of its page number taken out.
     */
    public function width(): int
    {
        $row = \count($this->lines);
        $width = $this->format->cellsPerLine;

        if (0 === $row && '' !== $this->printCells) {
            $width -= Braille::length($this->printCells) + self::NUMBER_GAP;
        }

        if ($row === $this->format->linesPerPage - 1 && $this->format->braillePageNumbers) {
            $width -= Braille::length($this->numbers->cells($this->number)) + self::NUMBER_GAP;
        }

        return $width;
    }

    /**
     * The lines left on the page.
     */
    public function remaining(): int
    {
        return $this->format->linesPerPage - \count($this->lines);
    }

    public function add(string $line): void
    {
        if ([] === $this->lines) {
            $this->topPage = $this->printPage;
            $this->topCells = $this->printCells;
        }

        $this->lines[] = $line;

        if (\count($this->lines) === $this->format->linesPerPage) {
            $this->finish();
        }
    }

    /**
     * A blank line, never at the top of a page.
     */
    public function blank(): void
    {
        if ([] !== $this->lines) {
            $this->add('');
        }
    }

    public function newPage(): void
    {
        if ([] !== $this->lines) {
            $this->finish();
        }
    }

    public function section(): void
    {
        $this->newPage();

        if (end($this->sections) !== \count($this->pages)) {
            $this->sections[] = \count($this->pages);
        }
    }

    /**
     * A print page starts: within a page, a line of dots 3-6 ending with its number; at the top of a page, or when that
     * line would be the last of its page, only the number at the end of the first line of the next page.
     */
    public function printPage(string $number, string $cells): void
    {
        if (!$this->format->printPageNumbers) {
            return;
        }

        $cells = substr($cells, 0, 3 * intdiv($this->format->cellsPerLine, 3));

        if ($this->remaining() < 2) {
            $this->finish();
        } elseif ([] !== $this->lines) {
            $this->add(str_repeat(Braille::HYPHEN, $this->format->cellsPerLine - Braille::length($cells) - 1).Braille::BLANK.$cells);
        }

        $this->printPage = $number;
        $this->printCells = $cells;
    }

    public function book(string $language, string $title): Book
    {
        if ([] !== $this->lines || [] === $this->pages) {
            $this->finish();
        }

        $starts = array_flip($this->sections);
        $size = $this->format->pagesPerVolume ?? \PHP_INT_MAX;
        $volumes = [];
        $sections = [];
        $section = [];
        $inVolume = 0;

        foreach ($this->pages as $index => $page) {
            if ($inVolume === $size) {
                $sections[] = new Section($section);
                $volumes[] = new Volume($sections);
                $sections = [];
                $section = [];
                $inVolume = 0;
            } elseif ([] !== $section && isset($starts[$index])) {
                $sections[] = new Section($section);
                $section = [];
            }

            $section[] = $page;
            ++$inVolume;
        }

        $sections[] = new Section($section);
        $volumes[] = new Volume($sections);

        return new Book($volumes, $this->format, $language, $title);
    }

    private function finish(): void
    {
        if (\count($this->pages) === $this->format->maxPages) {
            throw DocumentTooLong::over($this->format->maxPages);
        }

        $rows = $this->lines;
        $columns = $this->format->cellsPerLine;

        if ([] === $rows) {
            $this->topPage = $this->printPage;
            $this->topCells = $this->printCells;
        }

        if ('' !== $this->topCells) {
            array_unshift($rows, $this->pad(array_shift($rows) ?? '', $columns - Braille::length($this->topCells)).$this->topCells);
        }

        if ($this->format->braillePageNumbers) {
            while (\count($rows) < $this->format->linesPerPage) {
                $rows[] = '';
            }

            $number = $this->numbers->cells($this->number);
            $rows[] = $this->pad(array_pop($rows) ?? '', $columns - Braille::length($number)).$number;
        }

        $this->pages[] = new Page($rows, $this->number, $this->topPage);
        ++$this->number;
        $this->lines = [];
    }

    private function pad(string $row, int $cells): string
    {
        return $row.str_repeat(Braille::BLANK, max(0, $cells - Braille::length($row)));
    }
}
