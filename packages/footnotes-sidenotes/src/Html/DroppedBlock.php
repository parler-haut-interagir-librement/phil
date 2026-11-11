<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Html;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * Writes nothing: a note block is written where its placement says, not where it stands in the document.
 *
 * @internal
 */
final readonly class DroppedBlock implements BlockRenderer
{
    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        return '';
    }
}
