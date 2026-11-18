<?php

declare(strict_types=1);

namespace Phil\Slides\Infrastructure\Print;

use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\Slides\Domain\ValueObject\Deck;

/**
 * A deck as a PDF, through the print engine `phil/paged-media-bundle` configures: one page per slide, the speaker's
 * notes left out unless asked for. Registered only when that bundle is installed.
 */
final readonly class DeckPdf
{
    public function __construct(
        private PagedMedia $pagedMedia,
        private DeckPrint $print,
    ) {
    }

    /**
     * The PDF of a deck; with the notes, an upright page per slide holds the slide and its notes. The stages of a
     * pipeline — PDF/A, a signature — run on it after.
     */
    public function render(Deck $deck, bool $withNotes = false, string $filename = 'slides.pdf', ?Pipeline $pipeline = null): Output
    {
        $page = new PrintableHtml($this->print->body($deck, $withNotes), $this->print->stylesheets($deck, $withNotes), $this->print->language($deck), $deck->settings->title);

        return $this->pagedMedia->printHtml($page, $filename, $pipeline);
    }
}
