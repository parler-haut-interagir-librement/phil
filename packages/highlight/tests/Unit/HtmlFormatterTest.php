<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Phil\Highlight\HtmlFormatter;
use Phil\Highlight\LineOptions;
use Phil\Highlight\LineRanges;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlFormatter::class)]
final class HtmlFormatterTest extends TestCase
{
    public function testItEscapesTextsAndWrapsTypedTokens(): void
    {
        self::assertSame(
            '<span class="hl-keyword">if</span> a &lt; b &amp;&amp; &quot;c&quot; &#039;d&#039;<span class="hl-attr-name">x</span>',
            HtmlFormatter::format([new Token('if', TokenType::Keyword), new Token(' a < b && "c" \'d\''), new Token('x', TokenType::AttributeName)]),
        );
        self::assertSame('', HtmlFormatter::format([]));
    }

    public function testInvalidUtf8IsReplaced(): void
    {
        self::assertSame("a\u{FFFD}b", HtmlFormatter::format([new Token("a\xC3b")]));
    }

    public function testLinesAreWrappedAndJoinedByTheNewlinesOfTheSource(): void
    {
        self::assertSame(
            '<span class="hl-line"><span class="hl-keyword">a</span></span>'."\n".'<span class="hl-line"></span>'."\n".'<span class="hl-line">b &lt;</span>',
            HtmlFormatter::formatLines([new Token('a', TokenType::Keyword), new Token("\n\nb <")], new LineOptions()),
        );
        self::assertSame('<span class="hl-line"></span>', HtmlFormatter::formatLines([], new LineOptions()));
        self::assertSame('<span class="hl-line"></span>', HtmlFormatter::formatLines([new Token('')], new LineOptions()));
    }

    public function testATokenAcrossLinesIsCutIntoOnePiecePerLine(): void
    {
        self::assertSame(
            '<span class="hl-line">x <span class="hl-comment">/* a</span></span>'."\n".'<span class="hl-line"><span class="hl-comment">b */</span></span>',
            HtmlFormatter::formatLines([new Token('x '), new Token("/* a\nb */", TokenType::Comment)], new LineOptions()),
        );
    }

    public function testANewlineThatEndsTheSourceEndsTheLastLine(): void
    {
        self::assertSame(
            '<span class="hl-line">a</span>'."\n".'<span class="hl-line">b</span>'."\n",
            HtmlFormatter::formatLines([new Token("a\nb\n")], new LineOptions()),
        );
        self::assertSame(
            '<span class="hl-line">a</span>'."\n".'<span class="hl-line"></span>'."\n",
            HtmlFormatter::formatLines([new Token("a\n\n")], new LineOptions()),
        );
        self::assertSame(
            '<span class="hl-line"></span>'."\n",
            HtmlFormatter::formatLines([new Token("\n")], new LineOptions()),
        );
    }

    public function testLinesAreNumberedFromTheFirstNumber(): void
    {
        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">9</span>a</span>'."\n".'<span class="hl-line"><span class="hl-line-number">10</span>b</span>'."\n",
            HtmlFormatter::formatLines([new Token("a\nb\n")], LineOptions::numbered(9)),
        );
        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">0</span>a</span>',
            HtmlFormatter::formatLines([new Token('a')], LineOptions::numbered(0)),
        );
    }

    public function testHighlightedLinesAreCountedFromOneWhateverTheFirstNumber(): void
    {
        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">5</span>a</span>'."\n"
            .'<span class="hl-line hl-line-highlighted"><span class="hl-line-number">6</span>b</span>'."\n"
            .'<span class="hl-line hl-line-highlighted"><span class="hl-line-number">7</span>c</span>'."\n"
            .'<span class="hl-line"><span class="hl-line-number">8</span>d</span>',
            HtmlFormatter::formatLines([new Token("a\nb\nc\nd")], LineOptions::numbered(5)->withHighlighted(LineRanges::parse('2-3,9'))),
        );
    }

    /**
     * @return iterable<string, array{list<Token>, string}>
     */
    public static function annotations(): iterable
    {
        $comment = static fn (string $text): Token => new Token($text, TokenType::Comment);

        yield 'inserted, the comment and its spaces gone' => [
            [new Token('a();  '), $comment('// [!code ++]')],
            '<span class="hl-line hl-line-inserted">a();</span>',
        ];
        yield 'deleted' => [[new Token('a(); '), $comment('# [!code --]')], '<span class="hl-line hl-line-deleted">a();</span>'];
        yield 'highlighted' => [[new Token('a '), $comment('-- [!code highlight]')], '<span class="hl-line hl-line-highlighted">a</span>'];
        yield 'a block comment' => [[new Token('a '), $comment('/* [!code ++] */')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a block comment of many stars' => [[new Token('a '), $comment('/** [!code ++] **/')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a markup comment' => [[new Token('a '), $comment('<!-- [!code ++] -->')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a template comment' => [[new Token('a '), $comment('{# [!code ++] #}')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a template comment trimming' => [[new Token('a '), $comment('{#- [!code ++] -#}')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a semicolon comment' => [[new Token('a '), $comment(';; [!code ++]')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a percent comment' => [[new Token('a '), $comment('% [!code ++]')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a parenthesis comment' => [[new Token('a '), $comment('(* [!code ++] *)')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a comment written in kept' => [
            [new Token('a '), $comment('// why [!code ++]')],
            '<span class="hl-line hl-line-inserted">a <span class="hl-comment">// why</span></span>',
        ];
        yield 'a block comment written in kept' => [
            [new Token('a '), $comment('/* why [!code ++] */')],
            '<span class="hl-line hl-line-inserted">a <span class="hl-comment">/* why */</span></span>',
        ];
        yield 'two marks in one comment' => [
            [new Token('a '), $comment('// [!code ++] [!code highlight]')],
            '<span class="hl-line hl-line-highlighted hl-line-inserted">a</span>',
        ];
        yield 'no space before' => [[new Token('a'), $comment('//[!code ++]')], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'a tab before' => [[new Token("a\t"), $comment("//\t[!code ++]")], '<span class="hl-line hl-line-inserted">a</span>'];
        yield 'typed tokens before are kept whole' => [
            [new Token(';', TokenType::Punctuation), $comment('// [!code ++]')],
            '<span class="hl-line hl-line-inserted"><span class="hl-punctuation">;</span></span>',
        ];
        yield 'a line of nothing but the annotation' => [[new Token('    '), $comment('// [!code ++]')], '<span class="hl-line hl-line-inserted"></span>'];
        yield 'plain text after the comment kept' => [
            [new Token('a '), $comment('/* [!code ++] */'), new Token(' b')],
            '<span class="hl-line hl-line-inserted">a b</span>',
        ];
        yield 'code after a comment written in kept' => [
            [new Token('a '), $comment('/* why [!code ++] */'), new Token(' b')],
            '<span class="hl-line hl-line-inserted">a <span class="hl-comment">/* why */</span> b</span>',
        ];
        yield 'many tokens before the comment kept' => [
            [new Token('x', TokenType::Keyword), new Token(' y  '), $comment('// [!code ++]')],
            '<span class="hl-line hl-line-inserted"><span class="hl-keyword">x</span> y</span>',
        ];
        yield 'outside a comment, kept as written' => [
            [new Token('"[!code ++]"', TokenType::String)],
            '<span class="hl-line"><span class="hl-string">&quot;[!code ++]&quot;</span></span>',
        ];
        yield 'another word, kept as written' => [
            [new Token('a '), $comment('// [!code focus]')],
            '<span class="hl-line">a <span class="hl-comment">// [!code focus]</span></span>',
        ];
        yield 'a comment without annotation' => [
            [new Token('a '), $comment('// note')],
            '<span class="hl-line">a <span class="hl-comment">// note</span></span>',
        ];
    }

    /**
     * @param list<Token> $tokens
     */
    #[DataProvider('annotations')]
    public function testAnnotationsInCommentsMarkTheirLine(array $tokens, string $html): void
    {
        self::assertSame($html, HtmlFormatter::formatLines($tokens, new LineOptions(annotations: true)));
    }

    public function testAnnotationsAreLeftAsWrittenUnlessAsked(): void
    {
        self::assertSame(
            '<span class="hl-line">a <span class="hl-comment">// [!code ++]</span></span>',
            HtmlFormatter::formatLines([new Token('a '), new Token('// [!code ++]', TokenType::Comment)], new LineOptions()),
        );
    }

    public function testAnAnnotationMarksTheNumberOfLinesItSays(): void
    {
        $tokens = [new Token('// [!code highlight:3]', TokenType::Comment), new Token("\nb\nc\nd\ne // "), new Token('[!code --:0]', TokenType::Comment), new Token("\nf")];

        self::assertSame(
            '<span class="hl-line hl-line-highlighted"></span>'."\n"
            .'<span class="hl-line hl-line-highlighted">b</span>'."\n"
            .'<span class="hl-line hl-line-highlighted">c</span>'."\n"
            .'<span class="hl-line">d</span>'."\n"
            .'<span class="hl-line hl-line-deleted">e //</span>'."\n"
            .'<span class="hl-line">f</span>',
            HtmlFormatter::formatLines($tokens, new LineOptions(annotations: true)),
        );
    }

    public function testAnAnnotationWithoutNumberMarksItsLineAlone(): void
    {
        self::assertSame(
            '<span class="hl-line hl-line-deleted">a</span>'."\n".'<span class="hl-line">b</span>'."\n".'<span class="hl-line">c</span>',
            HtmlFormatter::formatLines([new Token('a '), new Token('// [!code --]', TokenType::Comment), new Token("\nb\nc")], new LineOptions(annotations: true)),
        );
    }

    public function testAnAnnotationStopsAtTheLastLine(): void
    {
        self::assertSame(
            '<span class="hl-line">a</span>'."\n".'<span class="hl-line hl-line-inserted">b</span>'."\n".'<span class="hl-line hl-line-inserted">c</span>'."\n",
            HtmlFormatter::formatLines([new Token("a\nb "), new Token('// [!code ++:9999]', TokenType::Comment), new Token("\nc\n")], new LineOptions(annotations: true)),
        );
    }

    public function testOverlappingAnnotationsKeepTheFarthestEnd(): void
    {
        $comment = static fn (string $text): Token => new Token($text, TokenType::Comment);

        self::assertSame(
            '<span class="hl-line hl-line-inserted">a</span>'."\n"
            .'<span class="hl-line hl-line-inserted">b</span>'."\n"
            .'<span class="hl-line hl-line-inserted">c</span>'."\n"
            .'<span class="hl-line">d</span>',
            HtmlFormatter::formatLines(
                [new Token('a '), $comment('// [!code ++:3]'), new Token("\nb "), $comment('// [!code ++]'), new Token("\nc\nd")],
                new LineOptions(annotations: true),
            ),
        );
    }

    public function testAnnotationsAndRangesTogether(): void
    {
        self::assertSame(
            '<span class="hl-line hl-line-highlighted hl-line-inserted"><span class="hl-line-number">1</span>a</span>',
            HtmlFormatter::formatLines([new Token('a '), new Token('// [!code ++]', TokenType::Comment)], new LineOptions(1, LineRanges::parse('1'), true)),
        );
    }
}
