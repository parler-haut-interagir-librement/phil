<?php

declare(strict_types=1);

namespace Phil\Ujo\Html\Renderer;

use Closure;
use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * The drawing a block keeps under `rendered` — the SVG of a QR code, a chart or a diagram, the MathML of a formula —
 * written as it is, once `$trusts` has proved its key for the block's current source; otherwise, and for a block
 * without a drawing, the renderer it stands in front of. A formula is wrapped in a `<div>`, a drawing in a
 * `<figure>` with the block's caption.
 *
 * The markup is written without being sanitized again: it is trusted because the key says the server wrote it, so
 * `$trusts` must refuse any key it did not issue.
 */
final readonly class StoredMarkupRenderer implements BlockRenderer
{
    /**
     * @param Closure(Block, string, string): bool $trusts whether the markup and its key, for this block, were written
     *                                                     by the server
     */
    public function __construct(
        private BlockRenderer $fallback,
        private Closure $trusts,
    ) {
    }

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        $stored = Rendering::array($block->data['rendered'] ?? null);
        $markup = $stored['markup'] ?? null;
        $key = $stored['key'] ?? null;

        if (!\is_string($markup) || !\is_string($key) || !($this->trusts)($block, $markup, $key)) {
            return $this->fallback->render($block, $rendering);
        }

        return 'math' === $block->type
            ? $rendering->element('div', $block, $markup)
            : $rendering->element('figure', $block, $markup.TextRenderer::caption($rendering->inline($block->data['caption'] ?? null)));
    }
}
