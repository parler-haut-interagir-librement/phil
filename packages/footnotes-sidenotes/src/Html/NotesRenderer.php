<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Html;

use Phil\FootnotesSidenotes\Exception\InvalidOptions;
use Phil\FootnotesSidenotes\Internal\ReferenceMarkup;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Resolution;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineSanitizer;

/**
 * Renders a document with its notes in the placement the caller chooses, through the given UJO renderer: the blocks are
 * drawn as usual, the note blocks are left where they are not shown, and each reference becomes a link and a backlink
 * pair, a margin note, or a note floated to the foot of the page.
 *
 * Identifiers start with a prefix, so several documents can share a page: `$idPrefix`, or one derived from the blocks
 * of the document. The same document shown twice on a page needs two prefixes.
 */
final readonly class NotesRenderer
{
    private const string PREFIX = '/^[A-Za-z][A-Za-z0-9_-]{0,31}$/D';

    /** Below every heading: nothing ends a section, and one region stands at the end of the document. */
    private const int NO_SECTION = 0;

    private InlineSanitizer $sanitizer;

    /**
     * @param string $profile the sanitizer's profile for the text of notes
     */
    public function __construct(
        private HtmlRenderer $renderer,
        private NoteLabels $labels = new NoteLabels(),
        ?InlineSanitizer $sanitizer = null,
        private string $profile = 'rich',
    ) {
        $this->sanitizer = $sanitizer ?? new InlineSanitizer();
    }

    /**
     * @param ?string $idPrefix     the start of every identifier: a letter, then up to 31 letters, digits, `_` or `-`
     * @param int     $sectionLevel for `EndOfSection`, the deepest level of a top-level `header` that starts a section, 1 to 6
     * @param int     $headingLevel the level of the title of a notes region, 1 to 6
     *
     * @throws InvalidOptions
     */
    public function render(Document $document, Resolution $resolution, Placement $placement = Placement::EndOfDocument, ?string $idPrefix = null, int $sectionLevel = 2, int $headingLevel = 2): string
    {
        foreach ([$sectionLevel, $headingLevel] as $level) {
            if ($level < 1 || $level > 6) {
                throw InvalidOptions::because('a heading level goes from 1 to 6');
            }
        }

        $prefix = $idPrefix ?? 'n'.hash('xxh32', implode(',', array_map(static fn (Block $block): string => $block->id, $document->blocks)));

        if (1 !== preg_match(self::PREFIX, $prefix)) {
            throw InvalidOptions::because('the prefix of identifiers is a letter, then up to 31 letters, digits, "_" or "-"');
        }

        [$blocks, $regions] = $this->place($document, $placement->isInFlow() ? [] : $resolution->notes, Placement::EndOfSection === $placement ? $sectionLevel : self::NO_SECTION);
        $writer = new NoteWriter($prefix, $this->labels, $this->sanitizer, $this->profile);

        $html = $this->renderer
            ->with('footnote', new DroppedBlock())
            ->with(NotesRegion::TYPE, new NotesRegion($writer, $regions, $headingLevel))
            ->withFilter(new ReferenceFilter($resolution, $writer, $placement))
            ->render($document->withBlocks($blocks));

        return Placement::Margin === $placement ? '<div class="hasSidenotes">'.$html.'</div>' : $html;
    }

    /**
     * The top-level blocks without their notes, with a region block before each section that ends with notes to show.
     *
     * @param list<Note> $notes        the notes to place in regions, in the order of their first reference
     * @param int        $sectionLevel the deepest level of a heading that ends a section
     *
     * @return array{list<Block>, array<string, list<Note>>}
     */
    private function place(Document $document, array $notes, int $sectionLevel): array
    {
        $blocks = [];
        $regions = [];
        $next = 0;

        foreach ($document->blocks as $index => $block) {
            if (ReferenceMarkup::isHeading($block, $sectionLevel)) {
                $this->closeSection($blocks, $regions, $notes, $next, $index);
            }

            if ('footnote' !== $block->type) {
                $blocks[] = $block;
            }
        }

        $this->closeSection($blocks, $regions, $notes, $next, \count($document->blocks));

        return [$blocks, $regions];
    }

    /**
     * Gives the notes first referenced before a top-level index, from the note `$next` on, a new region.
     *
     * @param list<Block>               $blocks
     * @param array<string, list<Note>> $regions
     * @param list<Note>                $notes
     */
    private function closeSection(array &$blocks, array &$regions, array $notes, int &$next, int $index): void
    {
        $section = [];

        while (isset($notes[$next]) && $notes[$next]->firstTopLevelIndex() < $index) {
            $section[] = $notes[$next++];
        }

        if ([] === $section) {
            return;
        }

        $name = 'notes-'.\count($regions);
        $regions[$name] = $section;
        $blocks[] = new Block($name, NotesRegion::TYPE);
    }
}
