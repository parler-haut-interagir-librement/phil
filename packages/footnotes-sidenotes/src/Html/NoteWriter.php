<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Html;

use Phil\FootnotesSidenotes\Internal\ReferenceMarkup;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\NoteReference;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\Rendering;

/**
 * Writes the HTML of notes and references for one rendering. Every text of a note goes through the inline sanitizer;
 * labels and identifiers are escaped by `Rendering::tag()`.
 *
 * @internal
 */
final readonly class NoteWriter
{
    private const string LINK_FREE = 'footnotes-sidenotes-link-free';

    private const string BACK_ARROW = "\u{21A9}\u{FE0E}";

    private InlineSanitizer $sanitizer;

    public function __construct(
        private string $prefix,
        private NoteLabels $labels,
        InlineSanitizer $sanitizer,
        private string $profile,
    ) {
        $this->sanitizer = $sanitizer->withProfile(self::LINK_FREE, $sanitizer->profile($profile)->without('link', 'mention', 'wikiLink'));
    }

    /**
     * A reference in the text, linked to its note; without a link inside another link, which HTML forbids.
     */
    public function reference(Note $note, int $occurrence, bool $inLink): string
    {
        $id = $this->referenceId($note, $occurrence);
        $name = $this->labels->referenceName($note->label);
        $label = InlineSanitizer::escape($note->label);

        if ($inLink) {
            return Rendering::tag('sup', ['id' => $id, 'class' => 'isNoteReference', 'aria-label' => $name], $label);
        }

        return Rendering::tag('sup', ['class' => 'isNoteReference'], Rendering::tag('a', ['id' => $id, 'href' => '#'.$this->noteId($note), 'role' => 'doc-noteref', 'aria-label' => $name], $label));
    }

    /**
     * A note beside the text, with its label and, for narrow viewports, a toggle: a checkbox and its label, which work
     * without a script. Everything is phrasing content, valid inside a paragraph.
     */
    public function sidenote(Note $note, bool $inLink): string
    {
        $toggle = $this->prefix.'-toggle-'.$note->id;
        $label = InlineSanitizer::escape($note->label);
        $switch = \sprintf('<input type="checkbox" class="isSidenoteSwitch" id="%s" aria-label="%s">', InlineSanitizer::escape($toggle), InlineSanitizer::escape($this->labels->toggleName($note->label)));
        $mark = Rendering::tag('label', ['class' => 'isSidenoteToggle', 'for' => $toggle], Rendering::tag('sup', ['class' => 'isNoteReference'], $label));
        $text = Rendering::tag('small', ['id' => $this->noteId($note), 'class' => 'isSidenote', 'role' => 'note'], $this->marker($note).' '.$this->body($note, $inLink));

        return Rendering::tag('span', ['class' => 'isSidenoteAnchor'], $switch.$mark.$text);
    }

    /**
     * A note at the foot of a printed page: the element GCPM floats there, in place of its reference. Its label is an
     * attribute the stylesheet reads, so the page shows the numbers of the resolver.
     */
    public function pageNote(Note $note, bool $inLink): string
    {
        return Rendering::tag('span', ['id' => $this->noteId($note), 'class' => 'isPageNote', 'data-note-label' => $note->label], $this->body($note, $inLink));
    }

    /**
     * A labelled region of notes, each with its backlinks.
     *
     * @param list<Note> $notes
     */
    public function region(string $name, array $notes, int $headingLevel): string
    {
        $region = $this->prefix.'-'.$name;
        $title = $region.'-title';
        $items = implode('', array_map($this->item(...), $notes));

        return Rendering::tag(
            'section',
            ['id' => $region, 'class' => 'isNotes', 'role' => 'doc-endnotes', 'aria-labelledby' => $title],
            Rendering::tag('h'.$headingLevel, ['id' => $title, 'class' => 'isNotesTitle'], InlineSanitizer::escape($this->labels->title))
            .Rendering::tag('ol', ['class' => 'isNotesList', 'role' => 'list'], $items)
        );
    }

    private function item(Note $note): string
    {
        $links = implode(' ', array_map(fn (NoteReference $reference): string => $this->backlink($note, $reference), $note->references));

        return Rendering::tag('li', ['id' => $this->noteId($note), 'class' => 'isNote'], $this->marker($note).' '.$this->body($note, false).' '.$links);
    }

    private function backlink(Note $note, NoteReference $reference): string
    {
        $repeated = \count($note->references) > 1;
        $rank = $repeated ? Rendering::tag('sup', [], (string) $reference->occurrence) : '';

        return Rendering::tag('a', [
            'class' => 'isNoteBacklink',
            'href' => '#'.$this->referenceId($note, $reference->occurrence),
            'role' => 'doc-backlink',
            'aria-label' => $this->labels->backlinkName($note->label, $reference->occurrence, $repeated),
        ], self::BACK_ARROW.$rank);
    }

    private function marker(Note $note): string
    {
        return Rendering::tag('span', ['class' => 'isNoteMarker'], InlineSanitizer::escape($note->label));
    }

    /**
     * The text of a note: sanitized, its own references out, on one line — a print engine may take a new line in the
     * source of a floated note for a forced break.
     */
    private function body(Note $note, bool $inLink): string
    {
        return ReferenceMarkup::collapse(ReferenceMarkup::strip($this->sanitizer->sanitize($note->html, $inLink ? self::LINK_FREE : $this->profile)));
    }

    private function noteId(Note $note): string
    {
        return $this->prefix.'-note-'.$note->id;
    }

    private function referenceId(Note $note, int $occurrence): string
    {
        return $this->prefix.'-ref-'.$note->id.'-'.$occurrence;
    }
}
