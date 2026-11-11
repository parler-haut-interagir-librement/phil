<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Html;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Renderer\StoredMarkupRenderer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\StoredMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredMarkupRenderer::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(StoredMarkup::class)]
#[CoversClass(HtmlRenderer::class)]
final class StoredMarkupRendererTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg"><title>x</title></svg>';

    public function testDrawsTheStoredMarkupOfTrustedKeys(): void
    {
        $seen = [];
        $renderer = HtmlRenderer::default()->withStoredMarkup(static function (Block $block, string $markup, string $key) use (&$seen): bool {
            $seen[] = [$block->id, $markup, $key];

            return 'good' === $key;
        });

        $html = $renderer->render($this->document([
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'https://example.com', 'rendered' => ['markup' => self::SVG, 'key' => 'good']]],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x', 'rendered' => ['markup' => '<math><mi>x</mi></math>', 'key' => 'good']]],
            ['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph TD', 'caption' => 'The <b>flow</b>', 'rendered' => ['markup' => self::SVG, 'key' => 'good']]],
            ['id' => 'c', 'type' => 'chart', 'data' => ['type' => 'bar', 'rendered' => ['markup' => self::SVG, 'key' => 'good']]],
        ]));

        self::assertSame(implode("\n", [
            '<figure class="ujo-qr-code">'.self::SVG.'</figure>',
            '<div class="ujo-math"><math><mi>x</mi></math></div>',
            '<figure class="ujo-mermaid">'.self::SVG.'<figcaption>The <b>flow</b></figcaption></figure>',
            '<figure class="ujo-chart">'.self::SVG.'</figure>',
        ]), $html);
        self::assertSame([['q', self::SVG, 'good'], ['m', '<math><mi>x</mi></math>', 'good'], ['d', self::SVG, 'good'], ['c', self::SVG, 'good']], $seen);
    }

    public function testFallsBackWhenTheKeyIsNotTrustedOrTheMarkupMissing(): void
    {
        $renderer = HtmlRenderer::default()->withStoredMarkup(static fn (Block $block, string $markup, string $key): bool => false);
        $html = $renderer->render($this->document([
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'abc', 'rendered' => ['markup' => '<script>x</script>', 'key' => 'forged']]],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x<y']],
        ]));

        self::assertSame(HtmlRenderer::default()->render($this->document([
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'abc']],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => 'x<y']],
        ])), $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testFallsBackOnAMisshapedDrawing(): void
    {
        $calls = 0;
        $renderer = HtmlRenderer::default()->withStoredMarkup(static function () use (&$calls): bool {
            ++$calls;

            return true;
        });
        $document = new Document([
            new Block('a', 'math', ['latex' => 'x', 'rendered' => 'svg']),
            new Block('b', 'math', ['latex' => 'x', 'rendered' => ['markup' => 1, 'key' => 'k']]),
            new Block('c', 'math', ['latex' => 'x', 'rendered' => ['markup' => 'm', 'key' => 2]]),
        ]);

        self::assertSame(implode("\n", array_fill(0, 3, '<div class="ujo-math">x</div>')), $renderer->render($document));
        self::assertSame(0, $calls);
    }

    public function testDrawsOnlyTheTypesAsked(): void
    {
        $renderer = HtmlRenderer::default()->withStoredMarkup(static fn (): bool => true, ['qrCode']);

        self::assertInstanceOf(StoredMarkupRenderer::class, $renderer->renderer('qrCode'));
        self::assertNotInstanceOf(StoredMarkupRenderer::class, $renderer->renderer('math'));
        self::assertSame('<div class="ujo-math">x</div>', $renderer->render(new Document([new Block('a', 'math', ['latex' => 'x', 'rendered' => ['markup' => 'M', 'key' => 'k']])])));
    }

    public function testRefusesATypeWithoutRenderer(): void
    {
        $this->expectException(UnknownBlockType::class);

        HtmlRenderer::default()->withStoredMarkup(static fn (): bool => true, ['nothing']);
    }

    public function testChecksTheShapeOfTheDrawing(): void
    {
        $normalizer = new Normalizer();
        $kept = $normalizer->normalize($this->document([['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'a', 'rendered' => ['key' => 'v1.AZaz09_-~', 'markup' => 'M']]]]));

        self::assertSame(['data' => 'a', 'rendered' => ['markup' => 'M', 'key' => 'v1.AZaz09_-~']], $kept->blocks[0]->data);

        foreach ([
            ['markup' => 'M'],
            ['key' => 'k'],
            ['markup' => 'M', 'key' => 'k k'],
            ['markup' => 'M', 'key' => str_repeat('k', 257)],
            ['markup' => str_repeat('é', StoredMarkup::MAX_MARKUP + 1), 'key' => 'k'],
            ['markup' => 'M', 'key' => 'k', 'other' => 1],
        ] as $rendered) {
            try {
                $normalizer->normalize($this->document([['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'a', 'rendered' => $rendered]]]));
                self::fail('The drawing '.json_encode(array_keys($rendered)).' is refused.');
            } catch (InvalidDocument) {
                self::addToAssertionCount(1);
            }
        }

        $fresh = new Normalizer(new BlockTypeRegistry(CommunityTypes::math(), CommunityTypes::mermaid(), CommunityTypes::chart(), LayoutTypes::qrCode()));

        foreach (['math' => ['latex' => 'x'], 'mermaid' => ['code' => 'x'], 'chart' => ['type' => 'bar'], 'qrCode' => ['data' => 'x']] as $type => $data) {
            try {
                $fresh->normalize($this->document([['id' => 'b', 'type' => $type, 'data' => [...$data, 'rendered' => 'svg']]]));
                self::fail('The drawing of '.$type.' is checked.');
            } catch (InvalidDocument) {
                self::addToAssertionCount(1);
            }
        }

        $largest = $normalizer->normalize($this->document([['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'a', 'rendered' => ['markup' => str_repeat('é', StoredMarkup::MAX_MARKUP), 'key' => str_repeat('k', 256)]]]]))->blocks[0]->data['rendered'] ?? null;
        self::assertIsArray($largest);
        self::assertSame(str_repeat('k', 256), $largest['key'] ?? null);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function document(array $blocks): Document
    {
        return Document::fromArray(['blocks' => $blocks]);
    }
}
