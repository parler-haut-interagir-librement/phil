<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\View;

/**
 * The reactions of one source in the picker, under their heading.
 */
final readonly class PickerGroup
{
    /**
     * @param list<ReactionItem> $items
     */
    public function __construct(
        public string $label,
        public array $items,
    ) {
    }
}
