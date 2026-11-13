<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Renderer;

use Override;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Contract\MermaidService;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;

/**
 * The SVG of a `mermaid` block's code, drawn by the diagram service, then rewritten by `phil/svg-sanitizer`: what
 * the service returns is never kept as it came. Its `<style>` element, where Mermaid writes its theme, is kept, every
 * rule placed under the diagram's root, so that none reaches the page.
 */
final readonly class MermaidRenderer implements MarkupRenderer
{
    public function __construct(
        private MermaidService $service,
        private SvgSanitizer $sanitizer = new SvgSanitizer(new Policy(styleElements: true)),
    ) {
    }

    #[Override]
    public function render(Block $block): string
    {
        $code = $block->data['code'] ?? null;

        if (!\is_string($code) || '' === trim($code)) {
            throw RenderingFailed::because('The diagram has no code.');
        }

        $sanitized = $this->sanitizer->sanitize($this->service->toSvg($code));

        if ($sanitized->isRefused()) {
            throw RenderingFailed::because('The diagram service answered with an image the sanitizer refuses.');
        }

        return $sanitized->svg;
    }
}
