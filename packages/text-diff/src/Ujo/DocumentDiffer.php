<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

use Phil\TextDiff\Myers;
use Phil\TextDiff\OperationKind;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Two documents compared block by block. At each level — the top of the document, the blocks of a column, of a
 * toggle — the identifiers of the blocks are compared in order: a block whose identifier is in both is kept or
 * changed, one that left its place and came back elsewhere is moved, the others are added or removed. Two blocks of
 * the same identifier are then compared value by value, `type`, `data` and `tunes`, and the blocks nested in them
 * level by level.
 *
 * @internal
 */
final readonly class DocumentDiffer
{
    public function __construct(
        private TextDiff $text,
        private Myers $myers,
    ) {
    }

    public function diff(Document $old, Document $new): DocumentDiff
    {
        return new DocumentDiff($this->level($old->blocks, $new->blocks, null));
    }

    /**
     * @param list<Block> $old
     * @param list<Block> $new
     *
     * @return list<BlockChange>
     */
    private function level(array $old, array $new, ?string $parent): array
    {
        $identifier = static fn (Block $block): string => $block->id;
        $steps = [];
        $deleted = [];
        $inserted = [];
        $oldIndex = 0;
        $newIndex = 0;

        foreach ($this->myers->diff(array_map($identifier, $old), array_map($identifier, $new)) as $operation) {
            foreach ($operation->tokens as $id) {
                $steps[] = [$operation->kind, $oldIndex, $newIndex];

                if (OperationKind::Delete === $operation->kind) {
                    $deleted[$id] = $oldIndex;
                } elseif (OperationKind::Insert === $operation->kind) {
                    $inserted[$id] = $id;
                }

                $oldIndex += OperationKind::Insert === $operation->kind ? 0 : 1;
                $newIndex += OperationKind::Delete === $operation->kind ? 0 : 1;
            }
        }

        $changes = [];

        foreach ($steps as [$kind, $at, $to]) {
            if (OperationKind::Equal === $kind) {
                array_push($changes, ...$this->compare($old[$at], $at, $new[$to], $to, $parent, false));
            } elseif (OperationKind::Insert === $kind) {
                $from = $deleted[$new[$to]->id] ?? null;
                array_push($changes, ...(null === $from
                    ? [new BlockChange(BlockChangeKind::Added, $new[$to]->id, $new[$to]->type, $parent, null, $to, null, $new[$to])]
                    : $this->compare($old[$from], $from, $new[$to], $to, $parent, true)));
            } elseif (!isset($inserted[$old[$at]->id])) {
                $changes[] = new BlockChange(BlockChangeKind::Removed, $old[$at]->id, $old[$at]->type, $parent, $at, null, $old[$at], null);
            }
        }

        return $changes;
    }

    /**
     * @return list<BlockChange>
     */
    private function compare(Block $old, int $oldIndex, Block $new, int $newIndex, ?string $parent, bool $moved): array
    {
        $fields = [];
        $nested = [];

        foreach (['type' => [$old->type, $new->type], 'data' => [$old->data, $new->data], 'tunes' => [$old->tunes, $new->tunes]] as $path => [$before, $after]) {
            $this->walk($path, $before, $after, $new->id, $fields, $nested);
        }

        $kind = match (true) {
            $moved => BlockChangeKind::Moved,
            [] === $fields && array_all($nested, static fn (BlockChange $block): bool => BlockChangeKind::Unchanged === $block->kind) => BlockChangeKind::Unchanged,
            default => BlockChangeKind::Changed,
        };

        return [new BlockChange($kind, $new->id, $new->type, $parent, $oldIndex, $newIndex, $old, $new, $fields), ...$nested];
    }

    /**
     * @param list<FieldChange> $fields
     * @param list<BlockChange> $nested
     */
    private function walk(string $path, mixed $old, mixed $new, string $owner, array &$fields, array &$nested): void
    {
        $oldBlocks = $this->blocks($old);
        $newBlocks = $this->blocks($new);

        if (null !== $oldBlocks && null !== $newBlocks) {
            array_push($nested, ...$this->level($oldBlocks, $newBlocks, $owner));

            return;
        }

        if (\is_array($old) && \is_array($new)) {
            foreach (array_keys($old + $new) as $key) {
                $this->walk($path.'.'.$key, $old[$key] ?? null, $new[$key] ?? null, $owner, $fields, $nested);
            }

            return;
        }

        $old = self::export($old);
        $new = self::export($new);

        if ($old !== $new) {
            $fields[] = new FieldChange($path, $old, $new, \is_string($old) && \is_string($new) ? $this->text->html($old, $new) : null);
        }
    }

    /**
     * The blocks a value holds, when it is a block or a list of blocks only.
     *
     * @return list<Block>|null
     */
    private function blocks(mixed $value): ?array
    {
        if ($value instanceof Block) {
            return [$value];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            return null;
        }

        $blocks = [];

        foreach ($value as $item) {
            if (!$item instanceof Block) {
                return null;
            }

            $blocks[] = $item;
        }

        return $blocks;
    }

    private static function export(mixed $value): mixed
    {
        if ($value instanceof Block) {
            return $value->toArray();
        }

        return \is_array($value) ? array_map(self::export(...), $value) : $value;
    }
}
