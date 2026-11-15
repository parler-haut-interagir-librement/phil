<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\CueText;
use Phil\TimedText\Internal\CueToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CueText::class)]
#[CoversClass(CueToken::class)]
final class CueTextTest extends TestCase
{
    public function testItReadsThePlainText(): void
    {
        self::assertSame('Hello & welcome <3', CueText::plain('<v Alice>Hello &amp; <b>welcome</b> &lt;3</v>'));
        self::assertSame('a < b', CueText::plain('a < b'));
        self::assertSame('a <> b', CueText::plain('a <> b'));
        self::assertSame("one\ntwo", CueText::plain("<00:00:01.000>one\n<i>two</i>"));
        self::assertSame('ab', CueText::plain('a<1:2>b'));
        self::assertSame('a<b', CueText::plain('a<<i>b'));
        self::assertSame('a<.x>b', CueText::plain('a<.x>b'));
        self::assertSame("'\u{00A0}", CueText::plain('&apos;&nbsp;'));
    }

    public function testItReadsTheVoices(): void
    {
        self::assertSame('Alice & Bob', CueText::speaker('<v.loud Alice &amp; Bob >Hi'));
        self::assertNull(CueText::speaker('Hi <v Alice>there'));
        self::assertNull(CueText::speaker('<v>Hi'));
        self::assertNull(CueText::speaker('<c Alice>Hi'));
        self::assertNull(CueText::speaker('</v Alice>'));
        self::assertNull(CueText::speaker(''));
        self::assertSame(['Alice', 'Bob'], CueText::voices('<v Alice>Hi</v> <v Bob>Hey <v Alice>Ho <v>x <c Carol>y'));
        self::assertSame([], CueText::voices('plain'));
    }

    public function testItBuildsCueTextFromPlainText(): void
    {
        self::assertSame('a &amp; b &lt;c&gt; --&gt; d', CueText::fromPlain('a & b <c> --> d'));
        self::assertSame("one\ntwo\nthree", CueText::fromPlain("one\r\n\r\n  \ntwo\rthree\n"));
        self::assertSame('<v Ann &lt;the&gt; host>Hi', CueText::fromPlain('Hi', " Ann\n<the>  host "));
        self::assertSame('Hi', CueText::fromPlain('Hi', '  '));
        self::assertSame('Hi', CueText::fromPlain('Hi'));
        self::assertSame('', CueText::fromPlain("\n\n"));
    }

    public function testItBuildsCueTextFromSrt(): void
    {
        self::assertSame('<b>bold</b> <i>it</i> <u>u</u> plain', CueText::fromSrt('<B>bold</B> <i>it</I> <u>u</u> <font color="#fff">plain</font>'));
        self::assertSame('top &amp; &lt;3', CueText::fromSrt('{\an8}top & <3'));
        self::assertSame('a{b}', CueText::fromSrt('a{b}'));
        self::assertSame("one\ntwo", CueText::fromSrt("one\r\n\r\ntwo"));
        self::assertSame('&lt;00:00:01.000&gt;x', CueText::fromSrt('<00:00:01.000>x'));
        self::assertSame('&lt;&gt;x', CueText::fromSrt('<>x'));
    }

    public function testItWritesSrtText(): void
    {
        self::assertSame('<b>bold</b> & <i>it</i> voice <3', CueText::toSrt('<b>bold</b> &amp; <i>it</i> <v Alice>voice</v> &lt;3'));
        self::assertSame('<b><i>x</i></b>', CueText::toSrt('<b><i>x</b>'));
        self::assertSame('<i>x</i>y', CueText::toSrt('<i>x</i></i>y'));
        self::assertSame('<u>open</u>', CueText::toSrt('<u>open'));
    }

    public function testItWritesHtml(): void
    {
        self::assertSame(
            '<span class="voice loud" data-voice="Alice &amp; &quot;Bob&quot;">Hi <b>there</b>&lt;script&gt;<br>'."\n".'<span class="a">x</span></span>',
            CueText::toHtml("<v.loud Alice &amp; \"Bob\">Hi <b>there</b>&lt;script&gt;\n<c.a.bad\">x</c>"),
        );
        self::assertSame('<ruby>漢<rt>kan</rt></ruby><u>u</u>', CueText::toHtml('<ruby>漢<rt>kan</rt></ruby><u>u</u>'));
        self::assertSame('<span lang="fr-CA">bonjour</span><span>x</span>', CueText::toHtml('<lang fr-CA>bonjour</lang><lang "><s>x</lang>'));
        self::assertSame('<span class="voice">x</span>', CueText::toHtml('<v>x'));
        self::assertSame('<span>x</span>', CueText::toHtml('<c>x</c>'));
        self::assertSame('<i>a</i>b', CueText::toHtml('<i>a<00:00:01.000></i>b</b>'));
        self::assertSame('<span class="voice" data-voice="A">&lt;img src=x onerror=alert(1)&gt;</span>', CueText::toHtml('<v A>&lt;img src=x onerror=alert(1)&gt;'));
        self::assertSame('&lt;3 &amp;amp;', CueText::toHtml('<3 &amp;amp;'));
        self::assertSame('x<i>open</i>', CueText::toHtml('<I>x</I><i>open'));
    }

    public function testItEscapesForWebVtt(): void
    {
        self::assertSame('&amp;lt; &lt;b&gt;', CueText::escape('&lt; <b>'));
    }

    public function testItSplitsIntoTokens(): void
    {
        $tokens = CueToken::split('<c.a.b note>x</c><1:00.000>');

        self::assertCount(4, $tokens);
        self::assertSame([CueToken::OPEN, 'c', ['a', 'b'], 'note', '<c.a.b note>'], [$tokens[0]->kind, $tokens[0]->name, $tokens[0]->classes, $tokens[0]->annotation, $tokens[0]->value]);
        self::assertSame([CueToken::TEXT, 'x'], [$tokens[1]->kind, $tokens[1]->value]);
        self::assertSame([CueToken::CLOSE, 'c', [], ''], [$tokens[2]->kind, $tokens[2]->name, $tokens[2]->classes, $tokens[2]->annotation]);
        self::assertSame([CueToken::TIMESTAMP, '<1:00.000>'], [$tokens[3]->kind, $tokens[3]->value]);
        self::assertSame(CueToken::TEXT, CueToken::split('<c..a.>')[0]->kind);
        self::assertSame("multi\nline", CueToken::split("<v multi\nline>")[0]->annotation);
        self::assertSame(CueToken::TIMESTAMP, CueToken::split('<1x>')[0]->kind);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostile(): iterable
    {
        yield 'script' => ['<script>alert(1)</script>'];
        yield 'attribute' => ['<c.x" onmouseover="alert(1)>x</c>'];
        yield 'voice quote' => ['<v a" onclick="alert(1)>x'];
        yield 'language' => ['<lang en" onclick="x>x'];
        yield 'image' => ['<img src=x onerror=alert(1)>'];
    }

    #[DataProvider('hostile')]
    public function testTheHtmlNeverCarriesAnAttributeItDidNotBuild(string $text): void
    {
        $html = CueText::toHtml($text);

        self::assertDoesNotMatchRegularExpression('/<(?!\/?(?:b|i|u|ruby|rt|span|br)[ >])/', $html);
        self::assertStringNotContainsString('onclick="', $html);
        self::assertStringNotContainsString('onmouseover="', $html);
        self::assertStringNotContainsString('<script', $html);
    }
}
