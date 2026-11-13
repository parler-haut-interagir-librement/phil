<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\TocEntry;

/**
 * The table of contents of a book built from the headings of its chapters.
 *
 * @internal
 */
final class HeadingToc
{
    /**
     * The table of contents built from the headings of the chapters down to the book's depth; a chapter without such a
     * heading is one entry, at the level of the book's highest heading.
     *
     * @param array<string, Chapter>   $documents
     * @param array<string, Converted> $converted
     * @param array<string, string>    $titles
     *
     * @return list<TocEntry>
     *
     * @throws InvalidBook
     */
    public static function build(Book $book, array $documents, array $converted, array $titles): array
    {
        $headings = [];

        foreach ($documents as $path => $chapter) {
            if ($chapter->inToc) {
                $headings[$path] = array_filter($converted[$path]->headings, static fn (Heading $heading): bool => $heading->level <= $book->tocDepth);
            }
        }

        $levels = array_map(static fn (Heading $heading): int => $heading->level, array_merge(...array_values($headings)));

        if ([] === $levels) {
            $paths = [] === $headings ? \array_slice(array_keys($titles), 0, 1) : array_keys($headings);

            return array_map(static fn (string $path): TocEntry => new TocEntry($titles[$path], $path), $paths);
        }

        $top = min($levels);
        $flat = [];

        foreach ($headings as $path => $list) {
            if ([] === $list) {
                $flat[] = [$top, $titles[$path], $path];
            }

            foreach ($list as $heading) {
                $flat[] = [$heading->level, $heading->text, $path.'#'.$heading->id];
            }
        }

        $entries = [];
        $position = 0;

        while ($position < \count($flat)) {
            [$entries[], $position] = self::entry($flat, $position);
        }

        return $entries;
    }

    /**
     * The entry at `$position` with the deeper entries that follow it, and the position after them.
     *
     * @param list<array{int, string, string}> $flat
     *
     * @return array{TocEntry, int}
     *
     * @throws InvalidBook
     */
    private static function entry(array $flat, int $position): array
    {
        [$level, $label, $href] = $flat[$position];
        $children = [];
        ++$position;

        while ($position < \count($flat) && $flat[$position][0] > $level) {
            [$children[], $position] = self::entry($flat, $position);
        }

        return [new TocEntry($label, $href, $children), $position];
    }
}
