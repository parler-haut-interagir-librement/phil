<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\RawNormalizer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inline::class)]
#[CoversClass(MarkdownReader::class)]
#[CoversClass(DefaultCompiler::class)]
#[CoversClass(Context::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Resolver::class)]
#[CoversClass(FrontMatter::class)]
#[CoversClass(RawNormalizer::class)]
final class ReaderPartsTest extends TestCase
{
    public function testTheReadersShareOneNormalizerThatAcceptsRawHtml(): void
    {
        self::assertSame(RawNormalizer::get(), RawNormalizer::get());
        self::assertSame('raw', RawNormalizer::get()->normalize(new Document([new Block('r', 'raw', ['html' => '<p>a</p>'])]))->blocks[0]->type);
    }

    public function testInlineContentKeepsItsMarkdownApart(): void
    {
        $inline = Inline::join([Inline::markdown('*a*'), Inline::html('<br>'), Inline::markdown('b')], '<hr>');

        self::assertSame('*a*b', $inline->source());
        self::assertSame([[false, '*a*'], [true, '<hr>'], [true, '<br>'], [true, '<hr>'], [false, 'b']], $inline->pieces);
        self::assertSame($inline, Inline::of($inline));
        self::assertSame([[true, '']], Inline::of('text')->pieces);
    }

    public function testBlocksAreNumberedInReadingOrder(): void
    {
        $document = new MarkdownReader()->read("# A\n\npara\n\n- x\n\n[Été  Ici][]\n\n[été ici]: /u")->document;

        self::assertSame(['b1', 'b2', 'b4', 'b5'], array_map(static fn (Block $block): string => $block->id, $document->blocks));
        self::assertSame('<a href="/u">Été  Ici</a>', $document->blocks[3]->data['text']);
    }

    public function testTheRendererCanBeReplaced(): void
    {
        $renderer = HtmlRenderer::default()->with('paragraph', new class implements BlockRenderer {
            public function render(Block $block, Rendering $rendering): string
            {
                return '<p>custom</p>';
            }
        });

        self::assertSame('<p>custom</p>', new DefaultCompiler($renderer)->compile('x', MarkupFormat::Markdown));
    }

    public function testTheMetadataIsReadApart(): void
    {
        self::assertSame([], new MarkdownReader()->read("---\n---\na: b\n---\nx")->metadata);
        self::assertSame(['a' => 'b'], new MarkdownReader()->read("---\na: b\n...\nx")->metadata);
        self::assertSame([], new MarkdownReader()->read("---\njust text\n---\nx")->metadata);
    }

    public function testTextThatIsNotUtf8IsKept(): void
    {
        self::assertSame("a\u{FFFD} b", new MarkdownReader()->read("a\xC3 b")->document->blocks[0]->data['text']);
    }

    public function testTheNormalizerCanBeReplaced(): void
    {
        $this->expectException(UnknownBlockType::class);
        new MarkdownReader(true, new Normalizer())->read('<div>x</div>');
    }

    public function testAListKeepsItsStart(): void
    {
        $meta = static fn (string $source): mixed => new MarkdownReader()->read($source)->document->blocks[0]->data['meta'];

        self::assertSame([], $meta('- a'));
        self::assertSame([], $meta('1. a'));
        self::assertSame(['start' => 0], $meta('0. a'));
        self::assertSame(['start' => 10], $meta('10. a'));
    }
}
