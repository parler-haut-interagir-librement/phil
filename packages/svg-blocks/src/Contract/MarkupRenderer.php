<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Contract;

use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;

/**
 * Draws the blocks of one type: the SVG or the MathML of the block's source, safe to write in a page as it is.
 */
interface MarkupRenderer
{
    /**
     * @throws RenderingFailed when the source cannot be drawn
     */
    public function render(Block $block): string;
}
