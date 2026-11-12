<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Ujo;

use Phil\AccessibilityCheck\Html\HtmlTarget;

/**
 * A field of a block's data that holds HTML, parsed: the rules that read HTML read it as they read a page, and locate
 * what they find in the block and the field.
 */
final readonly class Fragment
{
    public function __construct(
        public BlockVisit $visit,
        public string $pointer,
        public HtmlTarget $html,
    ) {
    }
}
