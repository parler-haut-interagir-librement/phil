<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Tests\Unit;

use Override;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgBlocks\Rendered;
use Phil\SvgBlocks\Renderer\ChartRenderer;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Renderer\QrCodeRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocks\Source;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\StoredMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockMarkup::class)]
#[CoversClass(Rendered::class)]
#[CoversClass(RenderingFailed::class)]
final class BlockMarkupTest extends TestCase
{
    private Signer $signer;

    #[Override]
    protected function setUp(): void
    {
        $this->signer = new Signer(str_repeat('s', 32));
    }

    public function testDrawsAndSignsEachKnownBlock(): void
    {
        $markup = $this->markup();
        $rendered = $markup->render($this->document([
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'HELLO']],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x^2']],
            ['id' => 'c', 'type' => 'chart', 'data' => ['type' => 'bar', 'data' => ['labels' => ['a'], 'datasets' => [['data' => [1]]]]]],
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'Hi']],
        ]));

        self::assertTrue($rendered->changed);
        self::assertSame([], $rendered->pending);
        self::assertSame([], $rendered->failures);

        foreach (\array_slice($rendered->document->blocks, 0, 3) as $block) {
            $stored = $this->stored($block);
            self::assertTrue($this->signer->trusts($block, $stored['markup'], $stored['key']));
        }

        self::assertStringStartsWith('<svg', $this->stored($rendered->document->blocks[0])['markup']);
        self::assertStringStartsWith('<math', $this->stored($rendered->document->blocks[1])['markup']);
        self::assertSame(['text' => 'Hi'], $rendered->document->blocks[3]->data);
        self::assertTrue($markup->draws('math'));
        self::assertFalse($markup->draws('paragraph'));
    }

    public function testKeepsACurrentDrawingAndRedrawsAStaleOrForgedOne(): void
    {
        $markup = $this->markup();
        $first = $markup->render($this->document([['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x']]]))->document;
        $again = $markup->render($first);

        self::assertFalse($again->changed);
        self::assertSame($first, $again->document);

        $edited = $first->withBlocks([$first->blocks[0]->withData([...$first->blocks[0]->data, 'latex' => 'y'])]);
        $redrawn = $markup->render($edited);

        self::assertTrue($redrawn->changed);
        self::assertStringContainsString('<mi>y</mi>', $this->stored($redrawn->document->blocks[0])['markup']);

        $forged = $markup->render($this->document([['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x', 'rendered' => ['markup' => '<script/>', 'key' => 'v1.forged']]]]));
        self::assertStringNotContainsString('<script', $this->stored($forged->document->blocks[0])['markup']);
    }

    public function testDrawsACurrentDrawingOnlyOnceAndAMisshapedOneAgain(): void
    {
        $counter = new class implements MarkupRenderer {
            public int $calls = 0;

            public function render(Block $block): string
            {
                ++$this->calls;

                return '<math/>';
            }
        };
        $markup = new BlockMarkup($this->signer, ['math' => $counter]);
        $drawn = $markup->render(self::document([['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x']]]))->document;
        $markup->render($drawn);
        self::assertSame(1, $counter->calls);

        $key = $this->signer->sign('math', 'x', '<math/>');

        foreach (['svg', ['markup' => 1, 'key' => $key], ['markup' => '<math/>', 'key' => 2], ['markup' => '<math/>'], ['key' => $key]] as $rendered) {
            $markup->render(new Document([new Block('m', 'math', ['latex' => 'x', 'rendered' => $rendered])]));
        }

        self::assertSame(6, $counter->calls);
    }

    public function testLeavesDeferredTypesPendingUntilAsked(): void
    {
        $diagram = new class implements MarkupRenderer {
            public int $calls = 0;

            public function render(Block $block): string
            {
                ++$this->calls;

                return '<svg/>';
            }
        };
        $markup = new BlockMarkup($this->signer, ['mermaid' => $diagram]);
        $stale = $this->document([['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph TD', 'caption' => '', 'rendered' => ['markup' => '<svg/>', 'key' => 'v1.old']]]]);

        $saved = $markup->render($stale);
        self::assertSame(['d'], $saved->pending);
        self::assertTrue($saved->changed, 'the stale drawing is dropped');
        self::assertArrayNotHasKey('rendered', $saved->document->blocks[0]->data);
        self::assertSame(0, $diagram->calls);

        $fresh = $this->document([['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph TD', 'caption' => '']]]);
        $unchanged = $markup->render($fresh);
        self::assertFalse($unchanged->changed);
        self::assertSame(['d'], $unchanged->pending);

        $drawn = $markup->render($saved->document, true);
        self::assertSame([], $drawn->pending);
        self::assertSame(1, $diagram->calls);
        self::assertSame('<svg/>', $this->stored($drawn->document->blocks[0])['markup']);
    }

    public function testListsTheBlocksThatCannotBeDrawn(): void
    {
        $rendered = $this->markup()->render($this->document([
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => '\nope', 'rendered' => ['markup' => 'old', 'key' => 'v1.x']]],
        ]));

        self::assertSame(['m' => 'The formula cannot be converted at byte 0: "\nope" is not supported.'], $rendered->failures);
        self::assertSame(['latex' => '\nope'], $rendered->document->blocks[0]->data);
        self::assertTrue($rendered->changed);

        $unchanged = $this->markup()->render($this->document([['id' => 'm', 'type' => 'math', 'data' => ['latex' => '\nope']]]));
        self::assertFalse($unchanged->changed);
    }

    public function testDrawsNestedBlocks(): void
    {
        $document = new Normalizer()->normalize($this->document([
            ['id' => 'cols', 'type' => 'columns', 'data' => ['cols' => [['blocks' => [['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'A']]]], ['blocks' => [['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'x']]]]]]],
        ]));
        $rendered = $this->markup()->render($document);
        $nested = $rendered->document->find('q');

        self::assertTrue($rendered->changed);
        self::assertNotNull($nested);
        self::assertArrayHasKey('rendered', $nested->data);
        self::assertSame($document->find('p'), $rendered->document->find('p'));
    }

    public function testRendersTheDrawingThroughUjo(): void
    {
        $document = $this->markup()->render($this->document([['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'A']]]))->document;
        $html = HtmlRenderer::default()->withStoredMarkup($this->signer->trusts(...))->render($document);

        self::assertStringStartsWith('<figure class="ujo-qr-code"><svg', $html);
    }

    public function testPreviewsOneBlock(): void
    {
        self::assertStringStartsWith('<math', $this->markup()->preview(new Block('m', 'math', ['latex' => 'x'])));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('Blocks of type "paragraph" are not drawn.');

        $this->markup()->preview(new Block('p', 'paragraph', ['text' => 'x']));
    }

    public function testRefusesADrawingLargerThanABlockKeeps(): void
    {
        $large = new class implements MarkupRenderer {
            public function render(Block $block): string
            {
                return str_repeat('é', StoredMarkup::MAX_MARKUP + 1);
            }
        };
        $fits = new class implements MarkupRenderer {
            public function render(Block $block): string
            {
                return str_repeat('é', StoredMarkup::MAX_MARKUP);
            }
        };

        self::assertSame(StoredMarkup::MAX_MARKUP, mb_strlen(new BlockMarkup($this->signer, ['math' => $fits])->preview(new Block('m', 'math', ['latex' => 'x']))));
        self::assertSame(['m' => 'The drawing is longer than the 1048576 characters a block may keep.'], new BlockMarkup($this->signer, ['math' => $large])->render($this->document([['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x']]]))->failures);
    }

    public function testSignsWithTheCurrentSource(): void
    {
        $document = $this->markup()->render($this->document([['id' => 'c', 'type' => 'chart', 'data' => ['type' => 'pie', 'data' => []]]]))->document;
        $block = $document->blocks[0];
        $stored = $this->stored($block);

        self::assertSame($this->signer->sign('chart', Source::of($block), $stored['markup']), $stored['key']);
    }

    /**
     * @return array{markup: string, key: string}
     */
    private function stored(?Block $block): array
    {
        $stored = $block?->data['rendered'] ?? null;
        self::assertIsArray($stored);
        self::assertIsString($stored['markup'] ?? null);
        self::assertIsString($stored['key'] ?? null);

        return ['markup' => $stored['markup'], 'key' => $stored['key']];
    }

    private function markup(): BlockMarkup
    {
        return new BlockMarkup($this->signer, [
            'qrCode' => new QrCodeRenderer(),
            'math' => new MathRenderer(),
            'chart' => new ChartRenderer(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function document(array $blocks): Document
    {
        return Document::fromArray(['blocks' => $blocks]);
    }
}
