<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\View;

/**
 * What one viewer sees of the reactions of a content: the bar, the picker's other reactions, who reacted, and whether
 * the viewer gave as many reactions as the site allows.
 */
final readonly class ReactionPanel
{
    /**
     * @param list<ReactionItem> $bar
     * @param list<PickerGroup>  $picker
     * @param list<ReactorsLine> $reactors
     */
    public function __construct(
        public bool $visible,
        public array $bar = [],
        public array $picker = [],
        public array $reactors = [],
        public bool $full = false,
    ) {
    }
}
