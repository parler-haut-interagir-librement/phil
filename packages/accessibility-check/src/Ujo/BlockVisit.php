<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Ujo;

use Phil\AccessibilityCheck\Location;
use Phil\Ujo\Block;

/**
 * A block of a UJO document as the check meets it: the block, where it is in the document, such as
 * `blocks[2].data.cols[0].blocks[1]`, and its language — the one of its `language` tune, else its parent's, else the
 * document's.
 */
final readonly class BlockVisit
{
    public function __construct(
        public Block $block,
        public string $pointer,
        public ?string $language,
    ) {
    }

    /**
     * The block, or one field of its data, such as `caption` or `files[1].caption`.
     */
    public function locate(string $field = ''): Location
    {
        return new Location(
            block: $this->block->id,
            blockType: $this->block->type,
            pointer: '' === $field ? $this->pointer : $this->pointer.'.data.'.$field,
        );
    }
}
