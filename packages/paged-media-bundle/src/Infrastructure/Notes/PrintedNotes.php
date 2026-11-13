<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Notes;

use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Stylesheet;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;

/**
 * A UJO (Universal JSON Output) document ready for the print engine, its notes resolved and placed, at the foot of the
 * page unless asked otherwise, with the screen and print stylesheets of `phil/footnotes-sidenotes`.
 *
 * Numbering each natural page is left to the engine, the only one to know where a page ends: `$countPerPage` adds the
 * sheet that gives the calls and the markers its footnote counter.
 */
final readonly class PrintedNotes
{
    public function __construct(
        private NoteResolver $resolver,
        private HtmlRenderer $renderer,
    ) {
    }

    /**
     * @param string  $locale       the language of the page, its notes' labels and numbering
     * @param bool    $countPerPage whether the engine numbers the notes, starting again on each page
     * @param ?string $title        the title of the page, which the engine writes in the PDF
     */
    public function page(Document $document, string $locale = 'en', Placement $placement = Placement::PageFoot, bool $countPerPage = false, ?string $title = null): NotesPage
    {
        $resolution = $this->resolver->resolve($document, $locale);
        $body = new NotesRenderer($this->renderer, NoteLabels::forLocale($locale))->render($document, $resolution, $placement);
        $sheets = [Stylesheet::Screen, Stylesheet::Print, ...($countPerPage ? [Stylesheet::PrintPerPage] : [])];

        return new NotesPage(new PrintableHtml($body, array_map(static fn (Stylesheet $sheet): string => $sheet->contents(), $sheets), $locale, $title), $resolution);
    }
}
