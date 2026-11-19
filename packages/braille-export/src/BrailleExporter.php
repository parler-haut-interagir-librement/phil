<?php

declare(strict_types=1);

namespace Phil\BrailleExport;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Exception\BrailleExportException;
use Phil\BrailleExport\Layout\Internal\Entry;
use Phil\BrailleExport\Layout\Internal\Flow;
use Phil\BrailleExport\Layout\Internal\PageNumbers;
use Phil\BrailleExport\Layout\Internal\PageSink;
use Phil\BrailleExport\Layout\Internal\Prepared;
use Phil\BrailleExport\Layout\Internal\Translated;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Translation\Braille;

/**
 * A document laid out in braille pages: its texts translated in one batch, then cut into lines and pages, with the
 * braille and print page numbers, headings kept with the line that follows them, lists and tables indented as their
 * kind asks. The book it gives is then written by `BrfWriter`, `PefWriter` or `UnicodeWriter`.
 *
 * The exporter holds no state between two documents.
 */
final readonly class BrailleExporter
{
    /** The blank cells on each side of a centred heading, at least. */
    private const int CENTRE_MARGIN = 3;

    /** The cells a line keeps for its text, whatever its indentation. */
    private const int MIN_TEXT = 2;

    public function __construct(
        private Translator $translator,
        private PageFormat $format = new PageFormat(),
    ) {
    }

    /**
     * @throws BrailleExportException
     */
    public function layout(Document $document, Contraction $contraction = Contraction::Uncontracted): Book
    {
        $prepared = Prepared::of($document);
        $braille = Translated::texts($this->translator, $prepared->texts, $document->language, $contraction);
        $sink = new PageSink($this->format, new PageNumbers($this->translator, $document->language, $contraction));

        foreach ($prepared->entries as $entry) {
            match ($entry->kind) {
                Entry::HEADING => $this->heading($sink, $entry, $braille[$entry->texts[0]]),
                Entry::PAGE_BREAK => $sink->newPage(),
                Entry::SECTION_BREAK => $sink->section(),
                Entry::PRINT_PAGE => $sink->printPage($entry->raw, $braille[$entry->texts[0]]),
                default => $this->text($sink, $entry, $braille),
            };
        }

        return $sink->book($document->language, $document->title);
    }

    /**
     * A heading, with a blank line before it unless it starts a page, a blank line after it at level 1, and on the next
     * page when the page cannot also hold the line that follows it; the blank line before counts even on an empty page,
     * where a new page changes nothing.
     */
    private function heading(PageSink $sink, Entry $entry, string $braille): void
    {
        $words = Flow::words($braille);

        if ([] === $words) {
            return;
        }

        $centred = 1 === $entry->level;
        $width = $centred ? $this->format->cellsPerLine - 2 * self::CENTRE_MARGIN : $this->format->cellsPerLine - $entry->indent;
        $needed = 1 + Flow::count($words, max(self::MIN_TEXT, $width)) + ($centred ? 1 : 0) + 1;

        if ($sink->remaining() < $needed) {
            $sink->newPage();
        }

        $sink->blank();
        $this->place($sink, $words, $entry->indent, $entry->runover, $centred);

        if ($centred) {
            $sink->blank();
        }
    }

    /**
     * @param list<string> $braille
     */
    private function text(PageSink $sink, Entry $entry, array $braille): void
    {
        foreach ($entry->texts as $position => $text) {
            $this->place($sink, Flow::words($braille[$text]), 0 === $position ? $entry->indent : $entry->runover, $entry->runover, false);
        }
    }

    /**
     * @param list<string> $words
     */
    private function place(PageSink $sink, array $words, int $indent, int $runover, bool $centred): void
    {
        $indentOf = static fn (int $line): int => min(0 === $line ? $indent : $runover, max(0, $sink->width() - self::MIN_TEXT));
        $width = $centred
            ? static fn (): int => max(self::MIN_TEXT, $sink->width() - 2 * self::CENTRE_MARGIN)
            : static fn (int $line): int => $sink->width() - $indentOf($line);

        foreach (Flow::lines($words, $width) as $line => $cells) {
            $before = $centred ? intdiv($sink->width() - Braille::length($cells), 2) : $indentOf($line);
            $sink->add(str_repeat(Braille::BLANK, max(0, $before)).$cells);
        }
    }
}
