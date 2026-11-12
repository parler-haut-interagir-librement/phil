<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\Rule\DuplicateId;
use Phil\AccessibilityCheck\Rule\FrameTitle;
use Phil\AccessibilityCheck\Rule\HtmlLang;
use Phil\AccessibilityCheck\Rule\LangChange;
use Phil\AccessibilityCheck\Rule\SvgName;
use Phil\AccessibilityCheck\Rule\TableHeaders;
use Phil\AccessibilityCheck\Rule\VideoCaptions;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TableHeaders::class)]
#[CoversClass(HtmlLang::class)]
#[CoversClass(LangChange::class)]
#[CoversClass(FrameTitle::class)]
#[CoversClass(VideoCaptions::class)]
#[CoversClass(DuplicateId::class)]
#[CoversClass(SvgName::class)]
final class StructureRulesTest extends RuleTestCase
{
    public function testTheRulesAreDescribed(): void
    {
        self::assertSame(['table-headers', SuccessCriterion::InfoAndRelationships, Severity::Error], [new TableHeaders()->id(), new TableHeaders()->criterion(), new TableHeaders()->severity()]);
        self::assertSame(['html-lang', SuccessCriterion::LanguageOfPage, Severity::Error], [new HtmlLang()->id(), new HtmlLang()->criterion(), new HtmlLang()->severity()]);
        self::assertSame(['lang-change', SuccessCriterion::LanguageOfParts, Severity::Error], [new LangChange()->id(), new LangChange()->criterion(), new LangChange()->severity()]);
        self::assertSame(['frame-title', SuccessCriterion::NameRoleValue, Severity::Error], [new FrameTitle()->id(), new FrameTitle()->criterion(), new FrameTitle()->severity()]);
        self::assertSame(['video-captions', SuccessCriterion::CaptionsPrerecorded, Severity::Error], [new VideoCaptions()->id(), new VideoCaptions()->criterion(), new VideoCaptions()->severity()]);
        self::assertSame(['duplicate-id', SuccessCriterion::NameRoleValue, Severity::Warning], [new DuplicateId()->id(), new DuplicateId()->criterion(), new DuplicateId()->severity()]);
        self::assertSame(['svg-name', SuccessCriterion::NonTextContent, Severity::Warning], [new SvgName()->id(), new SvgName()->criterion(), new SvgName()->severity()]);
    }

    public function testATableOfDataMarksItsHeaders(): void
    {
        self::assertSame(['table-headers.missing @ table:nth-of-type(1)', 'table-headers.missing @ table:nth-of-type(6) > tbody > tr:nth-of-type(1) > td > table:nth-of-type(2)'], self::html(new TableHeaders(), <<<'HTML'
            <table><tr><td>a</td></tr><tr><td>b</td></tr></table>
            <table><tr><th>h</th></tr><tr><td>b</td></tr></table>
            <table><tr><td>one row</td></tr></table>
            <table role="presentation"><tr><td>a</td></tr><tr><td>b</td></tr></table>
            <table hidden><tr><td>a</td></tr><tr><td>b</td></tr></table>
            <table><tr><td><table><tr><th>inner</th></tr></table><table><tr><td>x</td></tr><tr><td>y</td></tr></table></td></tr><tr><td role="rowheader">b</td></tr></table>
            HTML));
        self::assertSame(['table-headers.missing @ table'], self::html(new TableHeaders(), '<table><tr><td><table><tr><th>inner</th></tr></table></td></tr><tr><td>b</td></tr></table>'));
        self::assertSame([], self::html(new TableHeaders(), '<table><tr><td role="cell">a</td><td role="cell">b</td></tr></table>'));
        self::assertSame(['table-headers.missing @ table'], self::html(new TableHeaders(), '<table><tr><td>a</td></tr><tr><td role="cell">b</td></tr></table>'));
        self::assertSame(['table-headers.missing @ blocks[0]', 'table-headers.missing @ blocks[6]'], self::ujo(new TableHeaders(), [
            ['id' => 'a', 'type' => 'table', 'data' => ['withHeadings' => false, 'content' => [['a', 'b'], ['c', 'd']]]],
            ['id' => 'b', 'type' => 'table', 'data' => ['withHeadings' => true, 'content' => [['a'], ['c']]]],
            ['id' => 'c', 'type' => 'table', 'data' => ['content' => [['a']]]],
            ['id' => 'd', 'type' => 'table', 'data' => ['content' => [[['text' => 'a', 'header' => true]], ['c'], 'x']]],
            ['id' => 'e', 'type' => 'table', 'data' => ['content' => 'x']],
            ['id' => 'f', 'type' => 'paragraph', 'data' => ['content' => [['a'], ['b']]]],
            ['id' => 'g', 'type' => 'table', 'data' => ['withHeadings' => 'yes', 'content' => [[['text' => 'a', 'header' => 'yes']], ['c']]]],
        ]));
    }

    public function testAWholePageSaysItsLanguage(): void
    {
        self::assertSame(['html-lang.missing @ html'], self::html(new HtmlLang(), '<!doctype html><p>x</p>'));
        self::assertSame(['html-lang.missing @ html'], self::html(new HtmlLang(), '<html lang=" "><p>x</p></html>'));
        self::assertSame(['html-lang.invalid @ html (lang=français)'], self::html(new HtmlLang(), '<html lang=" français "></html>'));
        self::assertSame([], self::html(new HtmlLang(), '<html lang="fr-CA"></html>'));
        self::assertSame([], self::html(new HtmlLang(), '<p>A fragment</p>'));
    }

    public function testAPassageInAnotherLanguageSaysWhich(): void
    {
        self::assertSame([
            'lang-change.invalid @ span:nth-of-type(1) (lang=english!)',
            'lang-change.empty @ span:nth-of-type(2) !notice',
        ], self::html(new LangChange(), '<html lang="!!"><span lang=" english! ">a</span><span lang="">b</span><span lang="de">c</span><bdi>d</bdi><span dir="rtl">e</span></html>'));
        self::assertSame([
            'lang-change.invalid @ blocks[0] (lang=fr ca)',
            'lang-change.direction-only @ blocks[1] !notice',
            'lang-change.empty @ blocks[2] !notice',
            'lang-change.direction-only @ blocks[4].data.text bdi !notice',
            'lang-change.direction-only @ blocks[4].data.text bdo !notice',
            'lang-change.direction-only @ blocks[4].data.text span:nth-of-type(1) !notice',
            'lang-change.invalid @ blocks[4].data.text span:nth-of-type(3) (lang=x y)',
        ], self::ujo(new LangChange(), [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['language' => 'fr ca', 'direction' => 'ltr']]],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['direction' => 'rtl']]],
            ['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['language' => '']]],
            ['id' => 'd', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['language' => 'ar'], 'other' => 'x']],
            ['id' => 'e', 'type' => 'paragraph', 'data' => ['text' => '<bdi>a</bdi><bdo dir="rtl">b</bdo><span dir="rtl">c</span><span dir="rtl" lang="ar">d</span><span lang="x y">e</span><span class="x">f</span>'], 'tunes' => ['language' => 'notarray']],
            ['id' => 'f', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => []]],
        ]));
    }

    public function testAFrameSaysWhatItHolds(): void
    {
        self::assertSame(['frame-title.missing @ iframe:nth-of-type(1) (source=https://a.example)', 'frame-title.missing @ iframe:nth-of-type(5) (source=)'], self::html(new FrameTitle(), <<<'HTML'
            <iframe src="https://a.example">fallback text</iframe><iframe src="b" title=" Map "></iframe><iframe src="c" aria-label="Video"></iframe><iframe src="d" hidden></iframe><iframe title=" "></iframe>
            HTML));
        self::assertSame(['frame-title.missing @ frameset > frame:nth-of-type(1) (source=f.html)'], self::html(new FrameTitle(), '<!doctype html><html><frameset><frame src="f.html"><frame src="g.html" title="Menu"></frameset></html>'));
        self::assertSame(['frame-title.missing @ blocks[0].data.title (source=https://youtu.be/x)', 'frame-title.missing @ blocks[2].data.title (source=)'], self::ujo(new FrameTitle(), [
            ['id' => 'a', 'type' => 'embed', 'data' => ['source' => 'https://youtu.be/x', 'title' => ' ']],
            ['id' => 'b', 'type' => 'embed', 'data' => ['source' => 'https://youtu.be/y', 'title' => 'Trailer']],
            ['id' => 'c', 'type' => 'embed', 'data' => []],
            ['id' => 'd', 'type' => 'video', 'data' => []],
        ]));
    }

    public function testAVideoCarriesCaptions(): void
    {
        self::assertSame(['video-captions.missing @ video:nth-of-type(1) (source=a.mp4)', 'video-captions.missing @ video:nth-of-type(2) (source=b.webm)', 'video-captions.missing @ video:nth-of-type(3) (source=)'], self::html(new VideoCaptions(), <<<'HTML'
            <video src="a.mp4"><track kind="chapters" src="c.vtt"></video>
            <video><p>no source</p><source src="b.webm"><source src="b.mp4"></video>
            <video><track kind="descriptions"></video>
            <video src="d.mp4"><track src="d.vtt"></video>
            <video src="e.mp4"><track kind=" Captions " src="e.vtt"></video>
            <video src="f.mp4"><track kind="metadata"><track kind="subtitles"></video>
            <video src="g.mp4" hidden></video>
            HTML));
        self::assertSame(['video-captions.missing @ blocks[0].data.tracks (source=/a.webm)', 'video-captions.missing @ blocks[1].data.tracks (source=)'], self::ujo(new VideoCaptions(), [
            ['id' => 'a', 'type' => 'video', 'data' => ['sources' => [['url' => '/a.webm']], 'tracks' => [['kind' => 'chapters'], 'x', ['kind' => 3]]]],
            ['id' => 'b', 'type' => 'video', 'data' => ['tracks' => 'none']],
            ['id' => 'c', 'type' => 'video', 'data' => ['tracks' => [['url' => '/c.vtt']]]],
            ['id' => 'd', 'type' => 'video', 'data' => ['tracks' => [['kind' => 'metadata'], ['kind' => 'CAPTIONS']]]],
            ['id' => 'e', 'type' => 'audio', 'data' => []],
        ]));
    }

    public function testAnIdentifierNamesOneElement(): void
    {
        self::assertSame([
            'duplicate-id.duplicate @ div:nth-of-type(2) (id=a, count=3)',
            'duplicate-id.duplicate @ p:nth-of-type(2) (id=b, count=2) !error',
            'duplicate-id.duplicate @ span:nth-of-type(2) (id=c d, count=2) !error',
            'duplicate-id.duplicate @ i:nth-of-type(2) (id=e, count=2) !error',
            'duplicate-id.duplicate @ s:nth-of-type(2) (id=1, count=2)',
            'duplicate-id.duplicate @ b:nth-of-type(2) (id=f, count=2) !error',
        ], self::html(new DuplicateId(), <<<'HTML'
            <div id="a"></div><div id="a"></div><div id="a"></div><div id="unique"></div>
            <p id="b"></p><p id="b"></p><label for="b">L</label>
            <span id="c d"></span><span id="c d"></span><a href="#c%20d">to c d</a>
            <i id="e"></i><i id="e"></i><input aria-describedby="x  e"><s id="1"></s><s id="1"></s>
            <b id="f"></b><b id="f"></b><sup data-footnote="f">1</sup>
            HTML));
        self::assertSame(
            ['duplicate-id.duplicate @ u:nth-of-type(2) (id=q, count=2)', 'duplicate-id.duplicate @ em:nth-of-type(2) (id=r, count=2)'],
            self::html(new DuplicateId(), '<u id="q"></u><u id="q"></u><a href="/q">q</a><em id="r"></em><em id="r"></em><i title="#r">r</i>'),
        );
    }

    public function testTheIdentifiersOfADocumentAreThoseOfItsBlocksAndFields(): void
    {
        self::assertSame([
            'duplicate-id.duplicate @ blocks[1] (id=intro, count=3) !error',
            'duplicate-id.duplicate @ blocks[3] (id=fn1, count=2) !error',
        ], self::ujo(new DuplicateId(), [
            ['id' => 'a', 'type' => 'header', 'data' => ['text' => 'Intro'], 'tunes' => ['anchor' => ['anchor' => 'intro']]],
            ['id' => 'b', 'type' => 'header', 'data' => ['text' => 'Again'], 'tunes' => ['anchor' => ['anchor' => 'intro']]],
            ['id' => 'c', 'type' => 'footnote', 'data' => ['id' => 'fn1', 'text' => 'Note <span id="intro">x</span>']],
            ['id' => 'd', 'type' => 'footnote', 'data' => ['id' => 'fn1', 'text' => 'Again']],
            ['id' => 'e', 'type' => 'paragraph', 'data' => ['text' => 'See <a href="#intro">intro</a><sup data-footnote="fn1">1</sup>'], 'tunes' => ['anchor' => 'x']],
            ['id' => 'f', 'type' => 'header', 'data' => ['text' => 'Other'], 'tunes' => ['anchor' => ['anchor' => '']]],
            ['id' => 'g', 'type' => 'paragraph', 'data' => ['id' => 'ignored', 'text' => '<b id="lone">x</b>']],
        ]));
        self::assertSame(['duplicate-id.duplicate @ blocks[1].data.text b (id=z, count=2)'], self::ujo(new DuplicateId(), [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => '<b id="z">x</b>']],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => '<b id="z">y</b>']],
        ]));
    }

    public function testADrawingSaysWhatItShows(): void
    {
        self::assertSame(['svg-name.missing @ svg:nth-of-type(2)', 'svg-name.missing @ svg:nth-of-type(4)'], self::html(new SvgName(), <<<'HTML'
            <svg hidden></svg><svg><rect/></svg><svg><title>Chart</title></svg><svg><title> </title><svg><rect/></svg></svg><svg aria-label="Logo"></svg>
            <svg aria-hidden="true"></svg><svg role="presentation"></svg><svg role="none"></svg>
            <a href="/"><svg></svg></a><button><svg></svg></button>
            HTML));
        self::assertSame(['svg-name.missing @ blocks[0].data.rendered.markup svg'], self::ujo(new SvgName(), [
            ['id' => 'm', 'type' => 'mermaid', 'data' => ['code' => '<svg>', 'rendered' => ['markup' => '<svg><g/></svg>', 'key' => 'k']]],
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => '<svg></svg>', 'rendered' => ['markup' => '<svg><title>QR</title></svg>', 'key' => 'k']]],
        ]));
    }
}
