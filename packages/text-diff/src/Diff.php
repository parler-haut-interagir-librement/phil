<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * The difference between two texts: the operations that make the new text out of the old one, in reading order.
 *
 * Kept tokens and changed tokens alternate, and in each change the deleted tokens come before the inserted ones. White
 * space alone between two changes is part of them, so that a sentence rewritten word by word reads as one change.
 */
final readonly class Diff
{
    /**
     * @param list<Operation> $operations
     */
    public function __construct(
        public array $operations,
        public Tokenizer $tokenizer,
    ) {
    }

    /**
     * Whether the two texts are the same.
     */
    public function isIdentical(): bool
    {
        return array_all($this->operations, static fn (Operation $operation): bool => OperationKind::Equal === $operation->kind);
    }

    /**
     * The old text, given back from the operations.
     */
    public function old(): string
    {
        return $this->text(OperationKind::Delete);
    }

    /**
     * The new text, given back from the operations.
     */
    public function new(): string
    {
        return $this->text(OperationKind::Insert);
    }

    /**
     * The changes, in reading order: a deletion followed by an insertion is one replacement.
     *
     * @return list<Change>
     */
    public function changes(): array
    {
        $changes = [];
        $old = 0;
        $new = 0;
        $count = \count($this->operations);

        for ($index = 0; $index < $count; ++$index) {
            $operation = $this->operations[$index];
            $text = $operation->text();
            $length = mb_strlen($text);

            if (OperationKind::Equal === $operation->kind) {
                $old += $length;
                $new += $length;
            } elseif (OperationKind::Insert === $operation->kind) {
                $changes[] = new Change(ChangeKind::Insertion, '', $text, $old, $new);
                $new += $length;
            } else {
                $next = $this->operations[$index + 1] ?? null;

                if (OperationKind::Insert === $next?->kind) {
                    $inserted = $next->text();
                    $changes[] = new Change(ChangeKind::Replacement, $text, $inserted, $old, $new);
                    $new += mb_strlen($inserted);
                    ++$index;
                } else {
                    $changes[] = new Change(ChangeKind::Deletion, $text, '', $old, $new);
                }

                $old += $length;
            }
        }

        return $changes;
    }

    private function text(OperationKind $side): string
    {
        $text = '';

        foreach ($this->operations as $operation) {
            if ($side === $operation->kind || OperationKind::Equal === $operation->kind) {
                $text .= $operation->text();
            }
        }

        return $text;
    }
}
