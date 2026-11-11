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
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Type\EmbedServices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
final class HtmlReaderLossesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'a frame of a known service' => ['<p>a<iframe src="https://www.youtube.com/embed/x" width="560" height="315" title="Trailer"></iframe>b</p>', "<p class=\"ujo-paragraph\">a</p>\n<figure class=\"ujo-embed ujo-embed--youtube\"><iframe src=\"https://www.youtube.com/embed/x\" title=\"youtube\" width=\"560\" height=\"315\" loading=\"lazy\" allowfullscreen sandbox=\"allow-scripts allow-same-origin allow-popups allow-presentation\" referrerpolicy=\"strict-origin-when-cross-origin\"></iframe><a class=\"ujo-print-fallback\" href=\"https://www.youtube.com/embed/x\"><span class=\"ujo-print-fallback__title\">Trailer</span><span class=\"ujo-print-fallback__url\">https://www.youtube.com/embed/x</span></a></figure>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a frame of an unknown service' => ['<iframe src="https://example.com/x" title="Map"></iframe><iframe src="https://example.com/y"></iframe>', "<p class=\"ujo-paragraph\"><a href=\"https://example.com/x\">Map</a></p>\n<p class=\"ujo-paragraph\"><a href=\"https://example.com/y\">https://example.com/y</a></p>"];
        yield 'an embed rendered by phil/ujo' => ['<figure class="ujo-embed ujo-embed--vimeo"><iframe src="https://player.vimeo.com/video/1" title="vimeo"></iframe><a class="ujo-print-fallback" href="https://vimeo.com/1"><img src="/p.jpg"><span class="ujo-print-fallback__title">T</span><span class="ujo-print-fallback__url">https://vimeo.com/1</span></a><div class="ujo-print-fallback ujo-qr-code" data-qr-code="https://vimeo.com/1">x</div><figcaption><b>C</b></figcaption></figure>', '<figure class="ujo-embed ujo-embed--vimeo"><iframe src="https://player.vimeo.com/video/1" title="vimeo" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" referrerpolicy="strict-origin-when-cross-origin"></iframe><a class="ujo-print-fallback" href="https://vimeo.com/1"><img class="ujo-print-fallback__poster" src="/p.jpg" alt="" loading="lazy"><span class="ujo-print-fallback__title">T</span><span class="ujo-print-fallback__url">https://vimeo.com/1</span></a><div class="ujo-print-fallback ujo-qr-code" data-qr-code="https://vimeo.com/1">https://vimeo.com/1</div><figcaption><b>C</b></figcaption></figure>'];
        yield 'a video' => ['<video src="/a.webm" poster="/p.jpg" controls loop muted width="640" height="360" title="Clip"><source src="/a.mp4" type="Video/MP4"><source src="javascript:x"><source><track src="/fr.vtt" kind="captions" srclang="fr" label="Français" default><track src="/en.vtt" kind="other" srclang="en_US">Old browser</video>', '<figure class="ujo-video"><video controls loop muted poster="/p.jpg" width="640" height="360" preload="metadata" title="Clip"><source src="/a.webm"><source src="/a.mp4" type="video/mp4"><track kind="captions" src="/fr.vtt" srclang="fr" label="Français" default><track kind="subtitles" src="/en.vtt"></video><a class="ujo-print-fallback" href="/a.webm"><img class="ujo-print-fallback__poster" src="/p.jpg" alt="" loading="lazy"><span class="ujo-print-fallback__title">Clip</span><span class="ujo-print-fallback__url">/a.webm</span></a></figure>'];
        yield 'a sound in a figure' => ['<figure><audio src="/a.ogg" poster="/p.jpg" width="5"></audio><figcaption>Song</figcaption></figure>', '<figure class="ujo-audio"><audio preload="metadata"><source src="/a.ogg"></audio><a class="ujo-print-fallback" href="/a.ogg"><span class="ujo-print-fallback__url">/a.ogg</span></a><figcaption>Song</figcaption></figure>'];
        yield 'a player without source' => ['<p>a<video></video>b</p>', '<p class="ujo-paragraph">ab</p>'];
        yield 'a formula in its paragraph and alone' => ['<p>Area <math><mi>r</mi><msup><mi>x</mi><mn>2</mn></msup></math>.</p><math display="block"><semantics><mi>y</mi><annotation encoding="application/x-tex">y</annotation></semantics></math>', "<p class=\"ujo-paragraph\">Area <math><mi>r</mi><msup><mi>x</mi><mn>2</mn></msup></math>.</p>\n<div class=\"ujo-math\"><math display=\"block\"><semantics><mi>y</mi><annotation encoding=\"application/x-tex\">y</annotation></semantics></math></div>"];
        yield 'files alone in their paragraph' => ['<p><a href="/rules.pdf">The rules</a></p><p> <a href="/sheet" download="hero sheet.ODS">Sheet</a> </p><p><a href="/x.pdf">In</a> a sentence.</p><p><a href="/page">Page</a></p><p><a href="javascript:x" download>Bad</a></p>', "<a class=\"ujo-attaches\" href=\"/rules.pdf\" download data-extension=\"pdf\">The rules</a>\n<a class=\"ujo-attaches\" href=\"/sheet\" download data-extension=\"ods\">Sheet</a>\n<p class=\"ujo-paragraph\"><a href=\"/x.pdf\">In</a> a sentence.</p>\n<p class=\"ujo-paragraph\"><a href=\"/page\">Page</a></p>\n<p class=\"ujo-paragraph\">Bad</p>"];
        yield 'abbreviations' => ['<p><abbr title="Game master">GM</abbr>, <acronym title="NPC">NPC</acronym> and <abbr>HP</abbr></p>', '<p class="ujo-paragraph"><abbr title="Game master">GM</abbr>, <abbr title="NPC">NPC</abbr> and HP</p>'];
        yield 'languages' => ['<html lang="fr"><body><p>Il dit <span lang="en">hello</span>, <em lang="de">danke</em>, <span lang="fr">merci</span>, <span lang="en_US">x</span> <bdi>ابن</bdi> <bdo dir="rtl">abc</bdo></p><blockquote lang="en" dir="ltr"><p>Quote</p></blockquote><p lang="fr">même</p></body></html>', "<p class=\"ujo-paragraph\">Il dit <span lang=\"en\">hello</span>, <span lang=\"de\"><i>danke</i></span>, merci, x <span dir=\"auto\">ابن</span> <span dir=\"rtl\">abc</span></p>\n<figure class=\"ujo-quote\" lang=\"en\" dir=\"ltr\"><blockquote>Quote</blockquote></figure>\n<p class=\"ujo-paragraph\">même</p>"];
        yield 'ruby' => ['<p><ruby>漢<rp>(</rp><rt>kan</rt><rp>)</rp>字<rt>ji</rt></ruby></p>', '<p class="ujo-paragraph"><ruby>漢<rp>(</rp><rt>kan</rt><rp>)</rp>字<rp>(</rp><rt>ji</rt><rp>)</rp></ruby></p>'];
        yield 'links of other schemes and anchors' => ['<p id="top"><a href="tel:+15145550000">call</a> <a href="sms:+15145550000">text</a> <a href="geo:45.5,-73.5">map</a> <a href="xmpp:a@example.com">chat</a> <a href="#top">up</a> <a href="#nowhere">lost</a> <a href="ftp://x">ftp</a></p>', '<p id="top" class="ujo-paragraph"><a href="tel:+15145550000">call</a> <a href="sms:+15145550000">text</a> <a href="geo:45.5,-73.5">map</a> <a href="xmpp:a@example.com">chat</a> <a href="#top">up</a> lost ftp</p>'];
        yield 'a rich table' => ['<table><caption>Gear</caption><tr><th colspan="2" align="center">Item</th></tr><tr><th rowspan="2">Sword</th><td style="text-align:right">10</td></tr><tr><td class="ujo-align-left"><ul><li>a<ul><li>b</li></ul></li><li>c</li></ul><ol><li>d</li></ol></td></tr><tr><td colspan="2">x</td></tr></table>', '<table class="ujo-table"><caption class="ujo-table__caption">Gear</caption><thead><tr><th colspan="2" scope="colgroup" class="ujo-align-center">Item</th></tr></thead><tbody><tr><th rowspan="2" scope="rowgroup">Sword</th><td class="ujo-align-right">10</td></tr><tr><td class="ujo-align-left">• a<br>'."\u{A0}\u{A0}".'◦ b<br>• c<br>1. d</td></tr><tr><td colspan="2">x</td></tr></tbody></table>'];
        yield 'data attributes, comments and notes' => ['<!-- first --><p data-source="import" data-Bad-1="x" data-toc="y">a<!-- inside --></p><!-- between --><h2 data-level="2">b</h2><!-- last -->', "<p class=\"ujo-paragraph\">a</p>\n<h2 id=\"b\" class=\"ujo-header\">b</h2>"];
    }

    #[DataProvider('sources')]
    public function testItBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, HtmlRenderer::default()->render(new HtmlReader()->read($source)->document));
    }

    public function testTunesKeepTheDataTheLanguageAndTheComments(): void
    {
        $blocks = new HtmlReader()->read('<body><!-- first --><p data-source="import" data-Bad-1="x" data-toc="y" lang="en" dir="rtl">a<!-- inside --></p><!-- between --><h2 data-level="2">b</h2><!-- last -->')->document->blocks;

        self::assertSame(['note' => ['text' => "inside\nfirst"], 'language' => ['language' => 'en', 'direction' => 'rtl'], 'data' => ['source' => 'import', 'bad-1' => 'x']], $blocks[0]->tunes);
        self::assertSame(['data' => ['level' => '2'], 'note' => ['text' => "between\nlast"], 'anchor' => ['anchor' => 'b']], $blocks[1]->tunes);
    }

    public function testTheLanguageOfThePageIsItsMetadata(): void
    {
        self::assertSame(['language' => 'fr-CA'], new HtmlReader()->read('<html lang="fr-CA"><p>x</p></html>')->metadata);
        self::assertSame([], new HtmlReader()->read('<html lang="fr_CA"><p>x</p></html>')->metadata);
    }

    public function testWhatIsNotKeptIsListed(): void
    {
        $reading = new HtmlReader()->read('<html><head><title>T</title></head><body><div><p>a<script>x</script><b data-x="1">b</b></p><p><a href="ftp://x">f</a><a href="#none">n</a></p></div><iframe src="https://example.com/x"></iframe><svg><title>d</title></svg><video src="javascript:x"></video><span><!-- inline --></span><div><!-- alone --></div><form><input></form></body></html>');

        self::assertSame([
            ['script', 'body > div[1] > p[1] > script[1]', LossReason::UnknownElement],
            ['b', 'body > div[1] > p[1] > b[1]', LossReason::DataAttribute],
            ['a', 'body > div[1] > p[2] > a[1]', LossReason::RefusedScheme],
            ['a', 'body > div[1] > p[2] > a[2]', LossReason::UnknownAnchor],
            ['iframe', 'body > iframe[1]', LossReason::UnknownEmbed],
            ['svg', 'body > svg[1]', LossReason::SvgWithoutStore],
            ['video', 'body > video[1]', LossReason::RefusedScheme],
            ['video', 'body > video[1]', LossReason::MissingSource],
            ['#comment', 'body > span[1]', LossReason::Comment],
            ['#comment', 'body > div[2]', LossReason::Comment],
            ['form', 'body > form[1]', LossReason::UnknownElement],
        ], array_map(static fn (Loss $loss): array => [$loss->element, $loss->path, $loss->reason], $reading->losses));
    }

    public function testLossesAreCountedUpToTheirLimit(): void
    {
        self::assertCount(Context::MAX_LOSSES, new HtmlReader()->read('<p>'.str_repeat('<object></object>', Context::MAX_LOSSES + 5).'</p>')->losses);
    }

    public function testDeepElementsAreLost(): void
    {
        $losses = new HtmlReader()->read(str_repeat('<div>', 130).'<b>x</b>'.str_repeat('</div>', 130))->losses;

        self::assertSame(LossReason::Depth, $losses[0]->reason);
        self::assertSame('div', $losses[0]->element);
    }

    public function testAnInlineDrawingIsPurifiedStoredAndShownAsAnImage(): void
    {
        $store = new class implements ExtractedElementStore {
            /** @var list<array{string, string, string}> */
            public array $stored = [];

            public ?string $address = '/media/d.svg';

            #[Override]
            public function store(string $content, string $mediaType, string $name): ?string
            {
                $this->stored[] = [$content, $mediaType, $name];

                return $this->address;
            }
        };
        $reader = new HtmlReader(null, new EmbedServices(), $store);
        $reading = $reader->read('<p>a<svg viewBox="0 0 10 10" onload="x()"><title>Dot</title><circle cx="5" cy="5" r="4"/><script>y()</script><a xlink:href="javascript:z"><rect/></a></svg>b</p><p><a href="/x"><svg aria-label="Logo"><rect/></svg></a></p>');

        self::assertSame("<p class=\"ujo-paragraph\">a</p>\n<figure class=\"ujo-image\"><img src=\"/media/d.svg\" alt=\"Dot\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\">b</p>\n<p class=\"ujo-paragraph\"><a href=\"/x\">Logo</a></p>", HtmlRenderer::default()->render($reading->document));
        self::assertSame(['image/svg+xml', 'drawing-1.svg'], [$store->stored[0][1], $store->stored[0][2]]);
        self::assertStringContainsString('<circle', $store->stored[0][0]);
        self::assertStringNotContainsString('script', $store->stored[0][0]);
        self::assertStringNotContainsString('onload', $store->stored[0][0]);
        self::assertStringNotContainsString('javascript', $store->stored[0][0]);
        self::assertSame([], $reading->losses);

        $store->address = 'javascript:x';
        self::assertSame(LossReason::RefusedSvg, $reader->read('<svg><rect/></svg>')->losses[0]->reason);
        $store->address = null;
        self::assertSame(LossReason::RefusedSvg, $reader->read('<svg><rect/></svg>')->losses[0]->reason);
        self::assertSame(LossReason::RefusedSvg, $reader->read('<svg><foo><!ENTITY x "y"></foo></svg>')->losses[0]->reason);
    }
}
