<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Internal;

use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\NoteReference;
use Phil\FootnotesSidenotes\Numbering;
use Phil\FootnotesSidenotes\Problem;
use Phil\FootnotesSidenotes\ProblemKind;
use Phil\FootnotesSidenotes\Resolution;
use Phil\FootnotesSidenotes\Restart;
use Phil\Ujo\Block;

/**
 * One walk over a document, block by block and value by value, in reading order: the references, the notes, the runs
 * of numbering. An object lives for one resolution.
 *
 * @internal
 */
final class NoteCollector
{
    private const string MARK = 'data-footnote';

    /** @var array<string, array{id: string, block: Block}> the first block of each note identifier */
    private array $notes = [];

    /** @var list<array{id: string, block: string, top: int, scope: int}> */
    private array $references = [];

    /** @var list<Problem> */
    private array $problems = [];

    private int $scope = 0;

    private int $top = 0;

    public function __construct(
        private readonly NoteOptions $options,
    ) {
    }

    public function topLevel(Block $block, int $index): void
    {
        $this->top = $index;
        $this->block($block);
    }

    public function resolution(Numbering $numbering): Resolution
    {
        $problems = $this->problems;
        $byNote = [];

        foreach ($this->references as $reference) {
            if (isset($this->notes[$reference['id']])) {
                $byNote[$reference['id']][] = $reference;
            } else {
                $problems[] = new Problem(ProblemKind::MissingNote, $reference['id'], $reference['block']);
            }
        }

        $counters = [];
        $notes = [];

        foreach ($byNote as $references) {
            $id = $references[0]['id'];
            $scope = $references[0]['scope'];
            $counters[$scope] = ($counters[$scope] ?? 0) + 1;
            $notes[] = $this->note($id, $references, $counters[$scope], $numbering);

            if (\count($references) > 1) {
                $problems[] = new Problem(ProblemKind::RepeatedReference, $id, $references[1]['block'], \count($references));
            }
        }

        foreach ($this->notes as $note) {
            if (!isset($byNote[$note['id']])) {
                $problems[] = new Problem(ProblemKind::UnreferencedNote, $note['id'], $note['block']->id);
            }
        }

        return new Resolution($notes, $problems);
    }

    private function block(Block $block): void
    {
        if ($this->startsRun($block)) {
            ++$this->scope;
        }

        if (isset(ReferenceMarkup::LITERAL_TYPES[$block->type])) {
            return;
        }

        if ('footnote' === $block->type) {
            $this->noteBlock($block);

            return;
        }

        $this->values($block->data, $block->id);
    }

    private function startsRun(Block $block): bool
    {
        return match ($this->options->restart) {
            Restart::Chapter => ReferenceMarkup::isHeading($block, $this->options->chapterLevel),
            Restart::PageMarker => \in_array($block->type, $this->options->pageMarkers, true),
            Restart::Document => false,
        };
    }

    private function noteBlock(Block $block): void
    {
        $id = $block->data['id'] ?? null;

        if (!\is_string($id)) {
            return;
        }

        if (isset($this->notes[$id])) {
            $this->problems[] = new Problem(ProblemKind::DuplicateId, $id, $block->id);

            return;
        }

        $this->notes[$id] = ['id' => $id, 'block' => $block];
        $text = $block->data['text'] ?? null;

        foreach (\is_string($text) ? ReferenceMarkup::ids($text) : [] as $nested) {
            $this->problems[] = new Problem(ProblemKind::ReferenceInNote, $nested, $block->id);
        }
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function values(array $values, string $blockId): void
    {
        foreach ($values as $value) {
            if ($value instanceof Block) {
                $this->block($value);
            } elseif (\is_array($value)) {
                $this->values($value, $blockId);
            } elseif (\is_string($value) && str_contains($value, self::MARK)) {
                $this->text($value, $blockId);
            }
        }
    }

    private function text(string $html, string $blockId): void
    {
        foreach (ReferenceMarkup::ids($html) as $id) {
            $this->references[] = ['id' => $id, 'block' => $blockId, 'top' => $this->top, 'scope' => $this->scope];
        }
    }

    /**
     * @param non-empty-list<array{id: string, block: string, top: int, scope: int}> $references
     */
    private function note(string $id, array $references, int $number, Numbering $numbering): Note
    {
        $block = $this->notes[$id]['block'];
        $text = $block->data['text'] ?? '';
        $written = [];

        foreach ($references as $index => $reference) {
            $written[] = new NoteReference($reference['block'], $index + 1, $reference['top']);
        }

        return new Note($id, \is_string($text) ? $text : '', $block->id, $number, $numbering->format($number), $references[0]['scope'], $written);
    }
}
