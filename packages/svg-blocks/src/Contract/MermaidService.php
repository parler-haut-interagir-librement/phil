<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Contract;

use Phil\SvgBlocks\Exception\RenderingFailed;

/**
 * Draws a Mermaid diagram as SVG, outside PHP: Mermaid's layout runs in a browser engine. The answer is sanitized by
 * the caller before it is kept.
 */
interface MermaidService
{
    /**
     * @throws RenderingFailed when the service is unreachable, too slow, or refuses the diagram
     */
    public function toSvg(string $code): string;
}
