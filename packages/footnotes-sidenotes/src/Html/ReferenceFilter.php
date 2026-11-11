<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Html;

use Override;
use Phil\FootnotesSidenotes\Internal\ReferenceMarkup;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Resolution;
use Phil\Ujo\Html\HtmlFilter;

/**
 * Replaces each reference of the rendered HTML, already sanitized, by its output; a reference to a note the document
 * does not hold is left out, and reported by the resolution.
 *
 * @internal
 */
final readonly class ReferenceFilter implements HtmlFilter
{
    public function __construct(
        private Resolution $resolution,
        private NoteWriter $writer,
        private Placement $placement,
    ) {
    }

    #[Override]
    public function filter(string $html): string
    {
        $seen = [];

        return ReferenceMarkup::replace($html, function (string $id, bool $inLink) use (&$seen): string {
            $note = $this->resolution->note($id);

            if (!$note instanceof Note) {
                return '';
            }

            $seen[$id] = ($seen[$id] ?? 0) + 1;

            return $this->output($note, $seen[$id], $inLink);
        });
    }

    private function output(Note $note, int $occurrence, bool $inLink): string
    {
        if (1 < $occurrence) {
            return $this->writer->reference($note, $occurrence, $inLink);
        }

        return match ($this->placement) {
            Placement::Margin => $this->writer->sidenote($note, $inLink),
            Placement::PageFoot => $this->writer->pageNote($note, $inLink),
            default => $this->writer->reference($note, $occurrence, $inLink),
        };
    }
}
