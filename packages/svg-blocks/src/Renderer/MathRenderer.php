<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Renderer;

use Override;
use Phil\LatexMathMl\Exception\LatexMathMlException;
use Phil\LatexMathMl\LatexToMathMl;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;

/**
 * The MathML of a `math` block's formula, displayed on its own line, converted by `phil/latex-mathml`.
 */
final readonly class MathRenderer implements MarkupRenderer
{
    public function __construct(
        private LatexToMathMl $converter = new LatexToMathMl(),
    ) {
    }

    #[Override]
    public function render(Block $block): string
    {
        $latex = $block->data['latex'] ?? null;

        try {
            return $this->converter->convert(\is_string($latex) ? $latex : '');
        } catch (LatexMathMlException $latexMathMlException) {
            throw RenderingFailed::because($latexMathMlException->getMessage(), $latexMathMlException);
        }
    }
}
