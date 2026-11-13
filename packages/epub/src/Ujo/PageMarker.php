<?php

declare(strict_types=1);

namespace Phil\Epub\Ujo;

use Phil\Ujo\Block;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * Renders a `pageBreak` block as a placeholder that `UjoChapters` numbers as a page break marker.
 *
 * @internal
 */
final readonly class PageMarker implements BlockRenderer
{
    public const string PLACEHOLDER = '<span data-phil-epub-page=""></span>';

    public function render(Block $block, Rendering $rendering): string
    {
        return self::PLACEHOLDER;
    }
}
