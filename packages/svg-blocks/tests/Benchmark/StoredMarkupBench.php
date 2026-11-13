<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Tests\Benchmark;

use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Renderer\ChartRenderer;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Renderer\QrCodeRenderer;
use Phil\SvgBlocks\Signer;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;

/**
 * Every page that shows a document checks the key of each stored drawing before writing it: an HMAC of the source and
 * the markup, the hot path of reading. Saving draws only what changed; a save of an unchanged document checks keys.
 *
 * @Groups({"svg-blocks"})
 */
final readonly class StoredMarkupBench
{
    private HtmlRenderer $renderer;

    private BlockMarkup $markup;

    private Document $document;

    public function __construct()
    {
        $signer = new Signer(str_repeat('s', 32));
        $this->markup = new BlockMarkup($signer, ['qrCode' => new QrCodeRenderer(), 'math' => new MathRenderer(), 'chart' => new ChartRenderer()]);
        $this->renderer = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...));
        $this->document = $this->markup->render(Document::fromArray(['blocks' => [
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'https://example.com/a/fairly/long/address?with=query']],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => '\sum_{i=1}^{n} \frac{\sqrt{x_i}}{\left(a+b\right)^2}']],
            ['id' => 'c', 'type' => 'chart', 'data' => ['type' => 'bar', 'data' => ['labels' => range(1, 24), 'datasets' => [['label' => 'A', 'data' => range(1, 24)], ['label' => 'B', 'data' => range(24, 1)]]]]],
        ]]))->document;
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchRenderDocumentWithDrawings(): void
    {
        $this->renderer->render($this->document);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchSaveUnchangedDocument(): void
    {
        $this->markup->render($this->document);
    }
}
