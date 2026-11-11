<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit\Ujo;

use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Ujo\BlockChangeKind;
use Phil\TextDiff\Ujo\DocumentFormatter;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentFormatter::class)]
#[CoversClass(TextDiff::class)]
final class DocumentFormatterTest extends TestCase
{
    public function testEachBlockSaysWhatHappenedToIt(): void
    {
        $differ = new TextDiff();
        $diff = $differ->documents(
            new Document([new Block('a', 'paragraph', ['text' => 'Hello world']), new Block('b', 'header', ['text' => 'Title', 'level' => 2]), new Block('c', 'paragraph', ['text' => 'Gone']), new Block('e', 'paragraph', ['text' => 'Edited'])]),
            new Document([new Block('b', 'header', ['text' => 'Title', 'level' => 2]), new Block('a', 'paragraph', ['text' => 'Hello there world']), new Block('d', 'paragraph', ['text' => 'New']), new Block('e', 'paragraph', ['text' => 'Edited <i>twice</i>'])]),
        );

        self::assertSame(
            '<div class="text-diff-block text-diff-unchanged"><h2 id="b" class="ujo-header">Title</h2></div>'."\n"
            .'<div class="text-diff-block text-diff-removed"><del><p class="ujo-paragraph">Gone</p></del></div>'."\n"
            .'<div class="text-diff-block text-diff-moved"><p class="ujo-paragraph">Hello <ins>there </ins>world</p></div>'."\n"
            .'<div class="text-diff-block text-diff-added"><ins><p class="ujo-paragraph">New</p></ins></div>'."\n"
            .'<div class="text-diff-block text-diff-changed"><p class="ujo-paragraph">Edited <i><ins>twice</ins></i></p></div>'."\n",
            $differ->documentToHtml($diff),
        );
    }

    public function testNestedBlocksAreShownInsideTheirContainer(): void
    {
        $differ = new TextDiff();
        $html = $differ->documentToHtml($differ->documents(
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [new Block('p', 'paragraph', ['text' => 'In'])]])]),
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [new Block('p', 'paragraph', ['text' => 'Inside'])]])]),
        ));

        self::assertStringStartsWith('<div class="text-diff-block text-diff-changed">', $html);
        self::assertStringContainsString('<del>In</del><ins>Inside</ins>', $html);
        self::assertSame(1, substr_count($html, 'text-diff-block'));
    }

    public function testRendersWithTheRendererGiven(): void
    {
        $renderer = HtmlRenderer::default()->with('paragraph', new class implements BlockRenderer {
            public function render(Block $block, Rendering $rendering): string
            {
                return '<p>'.Rendering::text($block->data['text'] ?? '').'!</p>';
            }
        });
        $differ = new TextDiff();
        $document = new Document([new Block('a', 'paragraph', ['text' => 'x'])]);

        self::assertSame('<div class="text-diff-block text-diff-unchanged"><p>x!</p></div>'."\n", $differ->documentToHtml($differ->documents($document, $document), $renderer));
        self::assertSame('', $differ->documentToHtml($differ->documents(new Document(), new Document())));
    }

    public function testAnUnknownTypeIsReportedByTheRenderer(): void
    {
        $differ = new TextDiff();
        $document = new Document([new Block('a', 'nothing', [])]);

        $this->expectException(UnknownBlockType::class);
        $differ->documentToHtml($differ->documents($document, $document));
    }

    public function testBlocksAreNormalizedBeforeTheyAreRendered(): void
    {
        $differ = new TextDiff();
        $old = Document::fromArray(['blocks' => [['id' => 'a', 'type' => 'AnyButton', 'data' => ['link' => 'https://example.com/', 'text' => 'Go']]]]);
        $new = Document::fromArray(['blocks' => [['id' => 'a', 'type' => 'button', 'data' => ['link' => 'javascript:alert(1)', 'text' => 'Go']]]]);

        self::assertStringContainsString('href="https://example.com/"', $differ->documentToHtml($differ->documents($old, $old)));

        try {
            $differ->documentToHtml($differ->documents($old, $new));
            self::fail('A javascript: link is rendered.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertStringContainsString('blocks[0].data.link', $invalidDocument->getMessage());
        }
    }

    public function testTheNormalizerGivenChecksTheBlocks(): void
    {
        $differ = new TextDiff(normalizer: new Normalizer(new BlockTypeRegistry(StandardTypes::paragraph())));
        $document = new Document([new Block('a', 'header', ['text' => 'x'])]);

        $this->expectException(UnknownBlockType::class);
        $this->expectExceptionMessageIsOrContains('The block type "header" at blocks[0] is not registered.');

        $differ->documentToHtml($differ->documents($document, $document));
    }

    public function testEveryKindNamesItsClass(): void
    {
        self::assertSame(['unchanged', 'changed', 'added', 'removed', 'moved'], array_column(BlockChangeKind::cases(), 'value'));
    }
}
