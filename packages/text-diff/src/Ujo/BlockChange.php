<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

use Phil\Ujo\Block;

/**
 * One block of two compared documents: what happened to it, its identifier and type — the new ones, or the old ones
 * for a removed block —, the identifier of the block it is nested in, `null` at the top, its position among its
 * siblings in each document, the block itself in each document and the values that changed.
 *
 * A moved block is listed where it now stands; its fields say whether it was also edited.
 */
final readonly class BlockChange
{
    /**
     * @param list<FieldChange> $fields
     */
    public function __construct(
        public BlockChangeKind $kind,
        public string $id,
        public string $type,
        public ?string $parent,
        public ?int $oldIndex,
        public ?int $newIndex,
        public ?Block $old,
        public ?Block $new,
        public array $fields = [],
    ) {
    }

    /**
     * The block in the old document, as a list of zero or one block.
     *
     * @return list<Block>
     */
    public function before(): array
    {
        return $this->old instanceof Block ? [$this->old] : [];
    }

    /**
     * The block in the new document, as a list of zero or one block.
     *
     * @return list<Block>
     */
    public function after(): array
    {
        return $this->new instanceof Block ? [$this->new] : [];
    }
}
