<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ujo::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(Html::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(Tunes::class)]
#[CoversClass(BlockTypeRegistry::class)]
#[CoversClass(TuneRegistry::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Rendering::class)]
#[CoversClass(TextRenderer::class)]
#[CoversClass(ListRenderer::class)]
#[CoversClass(MediaRenderer::class)]
#[CoversClass(LayoutRenderer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
final class UjoTest extends TestCase
{
    private const string JSON = '{"time":1762387200000,"blocks":[{"id":"h","type":"header","data":{"text":"Hi <strong>you</strong>","level":2,"alignment":"center"}},{"id":"c","type":"checklist","data":{"items":[{"text":"a","checked":true}]}}],"version":"2.31.7"}';

    public function testUjoJsonIsReadCheckedWrittenRenderedAndConverted(): void
    {
        $ujo = new Ujo();
        $document = $ujo->decode(self::JSON);

        self::assertEquals(new Document([
            new Block('h', 'header', ['text' => 'Hi <b>you</b>', 'level' => 2], ['alignment' => ['alignment' => 'center']]),
            new Block('c', 'list', ['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'a', 'meta' => ['checked' => true], 'items' => []]]]),
        ], 1_762_387_200_000, '2.31.7'), $document);
        self::assertSame('{"time":1762387200000,"blocks":[{"id":"h","type":"header","data":{"text":"Hi <b>you</b>","level":2},"tunes":{"alignment":{"alignment":"center"}}},{"id":"c","type":"list","data":{"style":"checklist","meta":{},"items":[{"content":"a","meta":{"checked":true},"items":[]}]}}],"version":"2.31.7"}', $ujo->encode($document));
        self::assertStringStartsWith("{\n", $ujo->encode($document, true));
        self::assertSame($ujo->toHtml($document), $ujo->toHtml(self::JSON));
        self::assertSame("<h2 id=\"h\" class=\"ujo-header ujo-align-center\">Hi <b>you</b></h2>\n<ul class=\"ujo-list ujo-list--checklist\"><li data-checked=\"true\">a</li></ul>", $ujo->toHtml(self::JSON));
        self::assertSame("Hi you\n\n[x] a", $ujo->toText(self::JSON));
        self::assertSame("Hi you\n\n[x] a", $ujo->toText($document));
        self::assertEquals($document, $ujo->normalize(new Document($document->blocks, 1_762_387_200_000, '2.31.7')));
    }

    public function testADocumentIsCheckedBeforeItIsWrittenOrRendered(): void
    {
        $ujo = new Ujo();
        $unchecked = new Document([new Block('a', 'paragraph', ['text' => '<script>x</script>a', 'alignment' => 'left'])]);

        self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"a"},"tunes":{"alignment":{"alignment":"left"}}}]}', $ujo->encode($unchecked));
        self::assertSame('<p class="ujo-paragraph ujo-align-left">a</p>', $ujo->toHtml($unchecked));
        self::assertSame('a', $ujo->toText($unchecked));

        $this->expectException(InvalidDocument::class);

        $ujo->toHtml(new Document([new Block('a', 'header', ['text' => 't', 'level' => 7])]));
    }

    public function testEachPartIsReplaceable(): void
    {
        $ujo = new Ujo(
            new Normalizer(BlockTypeRegistry::default()->with(StandardTypes::raw())),
            HtmlRenderer::default()->with('paragraph', new LayoutRenderer()),
            new PlainText()->with('raw', static fn (): string => 'RAW'),
            new JsonDecoder(200),
        );

        self::assertSame('<div class="ujo-raw"><p>a</p></div>', $ujo->toHtml('{"blocks":[{"id":"r","type":"raw","data":{"html":"<p>a</p>"}}]}'));
        self::assertSame('{"blocks":[]}', $ujo->encode(new Document()));
        self::assertSame('RAW', $ujo->toText('{"blocks":[{"id":"r","type":"raw","data":{"html":"<p>a</p>"}}]}'));

        try {
            $ujo->toHtml('{"blocks":[{"id":"p","type":"paragraph","data":{"text":"a"}}]}');
            self::fail('The default renderer of paragraphs is used.');
        } catch (UnknownBlockType $unknownBlockType) {
            self::assertSame('No HTML renderer is registered for the block type "paragraph".', $unknownBlockType->getMessage());
        }

        $this->expectException(InvalidJson::class);

        $ujo->decode(str_pad('{"blocks":[]}', 201));
    }

    public function testTheDefaultsRefuseRawHtml(): void
    {
        $this->expectException(UnknownBlockType::class);

        new Ujo()->toHtml('{"blocks":[{"id":"r","type":"raw","data":{"html":"<p>a</p>"}}]}');
    }
}
