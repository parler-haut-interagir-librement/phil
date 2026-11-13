<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Renderer;

use Override;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgChart\Chart;
use Phil\SvgChart\Exception\SvgChartException;
use Phil\SvgChart\SvgChartWriter;
use Phil\Ujo\Block;

/**
 * The SVG of a `chart` block's configuration, drawn by `phil/svg-chart`.
 */
final readonly class ChartRenderer implements MarkupRenderer
{
    public function __construct(
        private SvgChartWriter $writer = new SvgChartWriter(),
    ) {
    }

    #[Override]
    public function render(Block $block): string
    {
        try {
            return $this->writer->write(Chart::fromConfig($block->data));
        } catch (SvgChartException $svgChartException) {
            throw RenderingFailed::because($svgChartException->getMessage(), $svgChartException);
        }
    }
}
