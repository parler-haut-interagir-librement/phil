<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

use Phil\Ujo\Block;

/**
 * Renders blocks of one or more types as HTML. A renderer escapes every plain text it writes and passes every HTML
 * value through `Rendering::inline()`, so its output holds only what it writes and what the sanitizer keeps.
 */
interface BlockRenderer
{
    public function render(Block $block, Rendering $rendering): string;
}
