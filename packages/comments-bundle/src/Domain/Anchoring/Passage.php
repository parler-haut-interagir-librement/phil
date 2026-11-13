<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Anchoring;

/**
 * One text where a passage can be: a field of a block, by the block's identifier and the field's path, or a plain
 * text, by neither. The text is the field's characters, its markup left out.
 */
final readonly class Passage
{
    public function __construct(
        public ?string $block,
        public ?string $field,
        public string $text,
    ) {
    }
}
