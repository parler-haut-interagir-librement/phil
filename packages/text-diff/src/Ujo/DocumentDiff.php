<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

/**
 * The difference between two UJO documents: every block of either, nested ones included, in the reading order of the
 * new document — a block before the blocks it holds, a removed block where it stood.
 */
final readonly class DocumentDiff
{
    /**
     * @param list<BlockChange> $blocks
     */
    public function __construct(
        public array $blocks,
    ) {
    }

    /**
     * The blocks that did not stay as they were.
     *
     * @return list<BlockChange>
     */
    public function changes(): array
    {
        return array_values(array_filter($this->blocks, static fn (BlockChange $block): bool => BlockChangeKind::Unchanged !== $block->kind));
    }

    /**
     * The blocks at the top of either document.
     *
     * @return list<BlockChange>
     */
    public function topLevel(): array
    {
        return array_values(array_filter($this->blocks, static fn (BlockChange $block): bool => null === $block->parent));
    }

    public function isIdentical(): bool
    {
        return [] === $this->changes();
    }
}
