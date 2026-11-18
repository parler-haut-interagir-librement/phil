<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

use Phil\Slides\Domain\Exception\DeckRefused;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Cuts a UJO (Universal JSON Output) document into the slides of a deck.
 *
 * The top-level blocks are read in order. A separator — `delimiter`, the `---` of Markdown, or `pageBreak` — ends a
 * slide; a heading up to the heading level starts one, unless the slide holds nothing shown yet; the split mode says
 * which of the two cut, and the directive `headingDivider` of the source cuts at both. A `pageStyle` block gives its
 * classes to the slide that holds it, and a `pageNumber` block of mode `skip` hides that slide's number; neither is
 * shown. A `container` block whose classes name the notes class holds the speaker's notes, and the `note` tune of any
 * block is a note too: wherever they are nested, both are taken out of what the audience sees. The `note` tune of a
 * block that closes a slide — a separator, or a heading that starts the next slide — belongs to the slide it closes,
 * as a comment written at the end of a slide is kept on the block after it. A note made of Marp directives alone,
 * `_class: lead` or `paginate: false`, is read as directives, not as a note (see CommentDirectives). A slide with
 * nothing shown and no note is dropped.
 *
 * The slicer keeps nothing between two calls.
 */
final readonly class Slicer
{
    public const int DEFAULT_MAX_SLIDES = 500;

    /** The deepest nesting searched for notes in a top-level block, each block, its data and each list in it a level. */
    public const int MAX_DEPTH = 64;

    private const string CLASS_NAME = '/^[A-Za-z0-9_-]{1,64}$/D';

    private const array SEPARATORS = ['delimiter', 'pageBreak'];

    public function __construct(
        private SplitMode $mode = SplitMode::Auto,
        private int $headingLevel = 2,
        private string $notesClass = 'notes',
        private int $maxSlides = self::DEFAULT_MAX_SLIDES,
        private AspectRatio $aspectRatio = AspectRatio::Wide,
    ) {
        if ($headingLevel < 1 || $headingLevel > 6) {
            throw InvalidOption::because(\sprintf('The heading level that starts a slide is 1 to 6, not %d.', $headingLevel));
        }

        if ($maxSlides < 1) {
            throw InvalidOption::because(\sprintf('A deck holds at least one slide, not %d.', $maxSlides));
        }

        if (1 !== preg_match(self::CLASS_NAME, $notesClass)) {
            throw InvalidOption::because(\sprintf('The class of the notes is 1 to 64 letters, digits, "_" or "-"; "%s" is not.', $notesClass));
        }
    }

    /**
     * The deck of a document, with the metadata of its source read as global directives.
     *
     * @param array<array-key, mixed> $metadata
     *
     * @throws DeckRefused
     */
    public function slice(Document $document, array $metadata = []): Deck
    {
        $directives = Directives::read($metadata, $this->aspectRatio);
        [$mode, $level] = null === $directives->headingDivider
            ? [$this->mode->resolve(array_any($document->blocks, $this->isSeparator(...))), $this->headingLevel]
            : [SplitMode::Both, $directives->headingDivider];

        $slides = [];
        $current = new SlideDraft();

        foreach ($document->blocks as $block) {
            if ($this->isSeparator($block) && $mode->cutsAtSeparators()) {
                $this->noteText($block, $current);
                $this->close($current, $slides);
                $current = $current->next();

                continue;
            }

            if ($mode->cutsAtHeadings() && $this->startsSlide($block, $level) && $current->shows()) {
                $this->noteText($block, $current);
                $block = $this->withoutNote($block);
                $this->close($current, $slides);
                $current = $current->next();
            }

            $this->take($block, $current);
        }

        $this->close($current, $slides);

        return new Deck($this->id($document), $slides, $directives->settings, array_values(array_unique([...$directives->ignored, ...$current->ignored])));
    }

    private function isSeparator(Block $block): bool
    {
        return \in_array($block->type, self::SEPARATORS, true);
    }

    private function startsSlide(Block $block, int $level): bool
    {
        $heading = $block->data['level'] ?? 2;

        return 'header' === $block->type && \is_int($heading) && $heading <= $level;
    }

    private function take(Block $block, SlideDraft $slide): void
    {
        if ('pageStyle' === $block->type) {
            $slide->classes = [...$slide->classes, ...$this->strings($block->data['classes'] ?? null)];
        } elseif ('pageNumber' === $block->type) {
            $slide->paginated = $slide->paginated && 'skip' !== ($block->data['mode'] ?? null);
        } elseif ($this->isNotes($block)) {
            $this->takeNotes($block, $slide, 1);
        } else {
            $slide->content[] = $this->strip($block, $slide, 1);
        }
    }

    private function takeNotes(Block $notes, SlideDraft $slide, int $depth): void
    {
        foreach ($notes->children() as $child) {
            $slide->notes[] = $this->strip($child, $slide, $depth + 1);
        }

        $this->noteText($notes, $slide);
    }

    private function isNotes(Block $block): bool
    {
        return 'container' === $block->type && \in_array($this->notesClass, $this->strings($block->data['classes'] ?? null), true);
    }

    /**
     * The block without its `note` tune and without the notes containers nested in it, both moved to the slide's notes.
     */
    private function strip(Block $block, SlideDraft $slide, int $depth): Block
    {
        $this->noteText($block, $slide);

        return new Block($block->id, $block->type, $this->stripData($block->data, $slide, $depth + 1), $this->withoutNote($block)->tunes);
    }

    private function withoutNote(Block $block): Block
    {
        $tunes = $block->tunes;
        unset($tunes['note']);

        return $block->withTunes($tunes);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function stripData(array $data, SlideDraft $slide, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw DeckRefused::tooDeep(self::MAX_DEPTH);
        }

        $kept = [];

        foreach ($data as $key => $value) {
            if ($value instanceof Block && $this->isNotes($value)) {
                $this->takeNotes($value, $slide, $depth + 1);

                continue;
            }

            $kept[$key] = match (true) {
                $value instanceof Block => $this->strip($value, $slide, $depth + 1),
                \is_array($value) => $this->stripData($value, $slide, $depth + 1),
                default => $value,
            };
        }

        return array_is_list($data) ? array_values($kept) : $kept;
    }

    private function noteText(Block $block, SlideDraft $slide): void
    {
        $note = $block->tunes['note'] ?? null;
        $text = \is_array($note) && \is_string($note['text'] ?? null) ? trim($note['text']) : '';

        if ('' === $text) {
            return;
        }

        $directives = CommentDirectives::read($text);

        if (!$directives instanceof CommentDirectives) {
            $slide->noteTexts[] = $text;

            return;
        }

        $slide->spotClasses = $directives->spotClasses ?? $slide->spotClasses;
        $slide->carriedClasses = $directives->carriedClasses ?? $slide->carriedClasses;
        $slide->spotPagination = $directives->spotPagination ?? $slide->spotPagination;
        $slide->carriedPagination = $directives->carriedPagination ?? $slide->carriedPagination;
        $slide->ignored = array_values(array_unique([...$slide->ignored, ...$directives->ignored]));
    }

    /**
     * @param list<Slide> $slides
     */
    private function close(SlideDraft $draft, array &$slides): void
    {
        if (!$draft->shows() && [] === $draft->notes && [] === $draft->noteTexts) {
            return;
        }

        if (\count($slides) >= $this->maxSlides) {
            throw DeckRefused::tooManySlides($this->maxSlides);
        }

        $slides[] = new Slide(\count($slides) + 1, $draft->content, $draft->notes, $draft->noteTexts, $draft->allClasses(), $draft->paginated, $this->title($draft->content), $draft->pagination());
    }

    /**
     * @param list<Block> $content
     */
    private function title(array $content): ?string
    {
        foreach ($content as $block) {
            if ('header' === $block->type && \is_string($block->data['text'] ?? null)) {
                $title = trim(html_entity_decode(strip_tags($block->data['text']), \ENT_QUOTES | \ENT_HTML5));

                return '' === $title ? null : $title;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => \is_string($value) && 1 === preg_match(self::CLASS_NAME, $value)));
    }

    /**
     * The identifier of a document's deck: a hash of its blocks, the same for the same blocks in every window.
     */
    private function id(Document $document): string
    {
        return hash('xxh128', (string) json_encode($document->toArray()['blocks'], \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }
}
