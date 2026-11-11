<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Override;
use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Contract\ExtractedElementStore;
use Phil\Markup\Reader\BBCode\Flow;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\Html\Walker;
use Phil\Markup\Reader\Loss;
use Phil\Markup\Reader\LossReason;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Block;
use Phil\Ujo\Type\EmbedServices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlReader::class)]
#[CoversClass(Walker::class)]
#[CoversClass(Loss::class)]
#[CoversClass(LossReason::class)]
#[CoversClass(Reading::class)]
#[CoversClass(Context::class)]
#[CoversClass(Autolink::class)]
#[CoversClass(Flow::class)]
#[CoversClass(Attributes::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(Inline::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Layout::class)]
#[CoversClass(Resolver::class)]
final class HtmlReaderEdgesTest extends TestCase
{
    public function testACommentNotesTheFirstBlockAfterItAndABlankOneNothing(): void
    {
        $reading = new HtmlReader()->read('<body><!--n--><center><p>a</p><p>b</p><p>c</p></center><!-- --><!--m--><span><p>d</p><p>e</p></span><p>f</p><!-- --></body>');

        self::assertSame([
            ['a', ['alignment' => ['alignment' => 'center'], 'note' => ['text' => 'n']]],
            ['b', ['alignment' => ['alignment' => 'center']]],
            ['c', ['alignment' => ['alignment' => 'center']]],
            ['d', ['note' => ['text' => 'm']]],
            ['e', []],
            ['f', []],
        ], array_map(static fn (Block $block): array => [$block->data['text'] ?? null, $block->tunes], $reading->document->blocks));
        self::assertSame([], $reading->losses);
    }

    public function testOnlyThePrintedFallbackOfAFigureIsReadBack(): void
    {
        $reading = new HtmlReader()->read('<figure><video src="/v.mp4"></video><a href="/x"><img src="/q.jpg"></a></figure><figure><iframe src="https://example.com/z"></iframe><figcaption>C</figcaption></figure><figure><video></video><figcaption>D</figcaption></figure><figure class="ujo-embed"><iframe src="https://player.vimeo.com/video/1"></iframe><a class="ujo-print-fallback" href=" https://vimeo.com/1 "><span class="ujo-print-fallback__title">T</span></a></figure><figure><iframe src="https://player.vimeo.com/video/1"></iframe><a class="ujo-print-fallback" href="/local"></a></figure><figure><audio src="/a.ogg"></audio><a class="ujo-print-fallback" href="/a.ogg"><img src="/q.jpg"></a></figure><figure><video src="/w.mp4"></video><a class="ujo-print-fallback" href="/elsewhere.mp4"></a></figure><p><b>a<!--   --></b></p>');
        $data = array_map(static fn (Block $block): array => $block->data, $reading->document->blocks);

        self::assertSame(['sources' => [['url' => '/v.mp4']], 'tracks' => [], 'controls' => false, 'loop' => false, 'muted' => false, 'caption' => ''], $data[0]);
        self::assertSame(['text' => '<a href="https://example.com/z">https://example.com/z</a>'], $data[1]);
        self::assertSame(['service' => 'vimeo', 'source' => 'https://vimeo.com/1', 'embed' => 'https://player.vimeo.com/video/1', 'caption' => '', 'title' => 'T'], $data[2]);
        self::assertSame(['service' => 'vimeo', 'source' => 'https://player.vimeo.com/video/1', 'embed' => 'https://player.vimeo.com/video/1', 'caption' => ''], $data[3]);
        self::assertSame(['sources' => [['url' => '/a.ogg']], 'tracks' => [], 'controls' => false, 'loop' => false, 'muted' => false, 'caption' => ''], $data[4]);
        self::assertSame(['sources' => [['url' => '/w.mp4']], 'tracks' => [], 'controls' => false, 'loop' => false, 'muted' => false, 'caption' => ''], $data[5]);
        self::assertSame(['text' => '<b>a</b>'], $data[6]);
        self::assertSame([LossReason::UnknownEmbed, LossReason::MissingSource], array_map(static fn (Loss $loss): LossReason => $loss->reason, $reading->losses));
    }

    public function testANoteKeepsTenThousandCharacters(): void
    {
        $note = new HtmlReader()->read('<body><!--z'.str_repeat('é', 10_000).'--><p>a</p></body>')->document->blocks[0]->tunes['note'] ?? null;

        self::assertSame(['text' => 'z'.str_repeat('é', 9_999)], $note);
    }

    public function testTheLanguageDirectionDataAndInlineElementsOfAParagraph(): void
    {
        $reading = new HtmlReader()->read('<p lang="en">a</p><p data-a1="1" data-1x="2" data--ab="3" data-a_b="4" data-long="'.str_repeat('é', 1000).'" data-longer="'.str_repeat('é', 1001).'">c</p><p '.implode(' ', array_map(static fn (int $number): string => \sprintf('data-k%d="%d"', $number, $number), range(1, 33))).'>d</p><p><bdo dir="RTL">abc</bdo> <abbr title=" GM ">gm</abbr> <ruby>漢<rt>k</rt></ruby> after<!--   --> <a name="x">t</a></p>');
        $blocks = $reading->document->blocks;

        self::assertSame(['language' => ['language' => 'en']], $blocks[0]->tunes);
        self::assertSame(['data' => ['a1' => '1', 'long' => str_repeat('é', 1000)]], $blocks[1]->tunes);
        $data = $blocks[2]->tunes['data'] ?? null;
        self::assertIsArray($data);
        self::assertCount(32, $data);
        self::assertSame('<span dir="rtl">abc</span> <abbr title="GM">gm</abbr> <ruby>漢<rp>(</rp><rt>k</rt><rp>)</rp></ruby> after t', $blocks[3]->data['text'] ?? null);
        self::assertSame([], $reading->losses);
    }

    public function testAFormulaInCapitalsAndASoundAlone(): void
    {
        $blocks = new HtmlReader()->read('<math display="BLOCK"><mi>x</mi></math><audio src="/a.ogg"></audio>')->document->blocks;

        self::assertSame(['math', 'audio'], [$blocks[0]->type, $blocks[1]->type]);
        self::assertSame([['url' => '/a.ogg']], $blocks[1]->data['sources'] ?? null);
    }

    public function testAPlayerAndItsPrintedFallbackAreTrimmedAndBounded(): void
    {
        $long = str_repeat('é', 201);
        $reading = new HtmlReader()->read('<figure><video src=" /v.webm " poster=" /p.jpg " title=" '.$long.' "><source src="/b.mp4" type=" video/mp4 "><source src="/c.mp4" type="video/'.str_repeat('x', 94).'"><track src="/t.vtt" kind="CAPTIONS" label=" '.str_repeat('é', 101).' "><img src="javascript:x"><source></video><a class="ujo-print-fallback" href=" /v.webm "><img src=" /q.jpg "><span class="ujo-print-fallback__title"> '.$long.' </span></a><figcaption> Song </figcaption></figure><video src="/w.mp4" title=" '.$long.' "></video>');
        $video = $reading->document->blocks[0]->data;

        self::assertSame([['url' => '/v.webm'], ['url' => '/b.mp4', 'type' => 'video/mp4'], ['url' => '/c.mp4', 'type' => 'video/'.str_repeat('x', 94)]], $video['sources'] ?? null);
        self::assertSame([['url' => '/t.vtt', 'kind' => 'captions', 'label' => str_repeat('é', 100), 'default' => false]], $video['tracks'] ?? null);
        self::assertSame([str_repeat('é', 200), 'Song', '/q.jpg'], [$video['title'] ?? null, $video['caption'] ?? null, $video['poster'] ?? null]);
        self::assertSame(str_repeat('é', 200), $reading->document->blocks[1]->data['title'] ?? null);
        self::assertSame([], $reading->losses);
    }

    public function testAPlayerKeepsEightSourcesSixteenTracksItsPosterAndItsTitle(): void
    {
        $video = new HtmlReader()->read('<video poster=" /p.jpg " title=" T " controls>'.implode('', array_map(static fn (int $number): string => '<source src="/'.$number.'.mp4">', range(1, 9))).implode('', array_map(static fn (int $number): string => '<track src="/'.$number.'.vtt">', range(1, 17))).'</video>')->document->blocks[0]->data;

        self::assertSame(array_map(static fn (int $number): array => ['url' => '/'.$number.'.mp4'], range(1, 8)), $video['sources'] ?? null);
        self::assertSame(array_map(static fn (int $number): array => ['url' => '/'.$number.'.vtt', 'kind' => 'subtitles', 'default' => false], range(1, 16)), $video['tracks'] ?? null);
        self::assertSame([true, 'T', '/p.jpg'], [$video['controls'] ?? null, $video['title'] ?? null, $video['poster'] ?? null]);
    }

    public function testAFrameIsTrimmedBoundedAndSizedInWholePixels(): void
    {
        $blocks = new HtmlReader()->read('<iframe src=" https://www.youtube.com/embed/x " title=" '.str_repeat('é', 201).' " width="0" height="10000"></iframe><iframe src="https://www.youtube.com/embed/y" width="1" height="10001"></iframe><iframe src="https://example.com/z" title=" Map "></iframe>')->document->blocks;

        self::assertSame(['https://www.youtube.com/embed/x', 10_000, null, str_repeat('é', 200)], [$blocks[0]->data['source'] ?? null, $blocks[0]->data['height'] ?? null, $blocks[0]->data['width'] ?? null, $blocks[0]->data['title'] ?? null]);
        self::assertSame([1, null], [$blocks[1]->data['width'] ?? null, $blocks[1]->data['height'] ?? null]);
        self::assertSame('<a href="https://example.com/z">Map</a>', $blocks[2]->data['text'] ?? null);
    }

    public function testTheLatexOfAFormulaIsItsTexAnnotationTrimmedAndBounded(): void
    {
        $math = new HtmlReader()->read('<math display="block"><semantics><mi>y</mi><annotation encoding="text/plain">no</annotation><annotation encoding="Application/X-TeX"> ab'.str_repeat('é', 20_000).' </annotation></semantics></math>')->document->blocks[0]->data;

        self::assertSame('ab'.str_repeat('é', 19_998), $math['latex'] ?? null);
    }

    public function testAFileIsNamedAndTitledTrimmedAndBounded(): void
    {
        $blocks = new HtmlReader()->read('<p><a href=" /rules.pdf "> The   rules </a></p><p><a href="/s" download=" sheet.ods ">S</a></p><p><a href="/n" download="a'.str_repeat('é', 300).'">'.str_repeat('é', 256).'</a></p><p><a href="/r" download="README">R</a></p><p><a href="/x" download="f.abcdefghijklmnop">X</a></p><p><a href="/x" download="f.abcdefghijklmnopq">Y</a></p>')->document->blocks;

        self::assertSame([
            [['url' => '/rules.pdf', 'name' => 'rules.pdf', 'extension' => 'pdf'], 'The rules'],
            [['url' => '/s', 'name' => 'sheet.ods', 'extension' => 'ods'], 'S'],
            [['url' => '/n', 'name' => 'a'.str_repeat('é', 254)], str_repeat('é', 255)],
            [['url' => '/r', 'name' => 'README'], 'R'],
            [['url' => '/x', 'name' => 'f.abcdefghijklmnop', 'extension' => 'abcdefghijklmnop'], 'X'],
            [['url' => '/x', 'name' => 'f.abcdefghijklmnopq'], 'Y'],
        ], array_map(static fn (Block $block): array => [$block->data['file'] ?? null, $block->data['title'] ?? null], $blocks));
    }

    public function testATableCaptionIsTrimmedAndACellAlignedByItsAttributeFirst(): void
    {
        $data = new HtmlReader()->read('<table><caption> Gear </caption><tr><td align="left" style="text-align:right">a</td><td align="RIGHT">b</td></tr></table>')->document->blocks[0]->data;

        self::assertSame(['Gear', [[['text' => 'a', 'align' => 'left'], ['text' => 'b', 'align' => 'right']]]], [$data['caption'] ?? null, $data['content'] ?? null]);
    }

    public function testFormulasAndRubiesAreReadAsDeepAsTheReaderReads(): void
    {
        $reader = new HtmlReader();
        $formula = static fn (int $depth): string => self::string($reader->read('<math display="block">'.str_repeat('<mrow>', $depth).'<mi>x</mi>'.str_repeat('</mrow>', $depth).'</math>')->document->blocks[0]->data['mathml'] ?? null);
        $ruby = static fn (int $depth): string => self::string($reader->read('<p>'.str_repeat('<ruby>', $depth).'a<rt>b</rt>'.str_repeat('</ruby>', $depth).'</p>')->document->blocks[0]->data['text'] ?? null);

        self::assertSame([126, 1], [substr_count($formula(126), '<mrow'), substr_count($formula(126), '<mi')]);
        self::assertSame([127, 0], [substr_count($formula(127), '<mrow'), substr_count($formula(127), '<mi')]);
        self::assertSame(1, substr_count($ruby(126), '<rt>'));
        self::assertSame(0, substr_count($ruby(127), '<rt>'));
    }

    public function testManyFormulasAndRubiesSideBySideAreAllRead(): void
    {
        $blocks = new HtmlReader()->read('<math display="block">'.str_repeat('<mi>x</mi>', 70).'</math><p>'.str_repeat('<ruby>a<rt>b</rt></ruby>', 70).'</p><p>c</p>')->document->blocks;

        self::assertSame(['math', 'paragraph', 'paragraph'], array_map(static fn (Block $block): string => $block->type, $blocks));
        self::assertSame(70, substr_count(self::string($blocks[0]->data['mathml'] ?? null), '<mi>'));
        self::assertSame(70, substr_count(self::string($blocks[1]->data['text'] ?? null), '<rt>'));
    }

    public function testADrawingIsGivenItsNamespacesAndItsTitleAsItsText(): void
    {
        $store = new class implements ExtractedElementStore {
            /** @var list<string> */
            public array $stored = [];

            #[Override]
            public function store(string $content, string $mediaType, string $name): string
            {
                $this->stored[] = $content;

                return '/d.svg';
            }
        };
        $reader = new HtmlReader(null, new EmbedServices(), $store);
        $reading = $reader->read('<svg><rect/></svg><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><rect/></svg><svg><a xlink:href="#a"><rect/></a></svg><p><svg aria-label="L"><title> T </title><rect/></svg></p>');

        self::assertSame([
            '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><a xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-a"><rect/></a></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><title> T </title><rect/></svg>',
        ], $store->stored);
        self::assertSame('T', $reading->document->blocks[3]->data['alt'] ?? null);
        self::assertSame([], $reading->losses);
    }

    private static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
