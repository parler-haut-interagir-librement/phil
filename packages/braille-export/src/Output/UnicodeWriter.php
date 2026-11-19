<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Output;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Book\Page;

/**
 * A book as Unicode braille text, for a preview on a screen: the rows of each page, one per line.
 */
final readonly class UnicodeWriter
{
    /**
     * Each page, its rows separated by line feeds.
     *
     * @return list<string>
     */
    public function pages(Book $book): array
    {
        return array_map(static fn (Page $page): string => implode("\n", $page->rows), $book->pages());
    }

    /**
     * The whole book, its pages separated by `$separator`, a blank line by default.
     */
    public function write(Book $book, string $separator = "\n\n"): string
    {
        return implode($separator, $this->pages($book));
    }
}
