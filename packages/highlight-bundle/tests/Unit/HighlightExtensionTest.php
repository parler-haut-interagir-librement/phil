<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use InvalidArgumentException;
use Phil\Highlight\Exception\InvalidLineRanges;
use Phil\Highlight\Highlighter;
use Phil\Highlight\LineOptions;
use Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Markup;

#[CoversClass(HighlightExtension::class)]
final class HighlightExtensionTest extends TestCase
{
    public function testTheCodeIsColouredInItsLanguage(): void
    {
        $extension = new HighlightExtension(Highlighter::default());

        self::assertSame('<span class="hl-keyword">echo</span> <span class="hl-string">&quot;&lt;b&gt;&quot;</span><span class="hl-punctuation">;</span>', $extension->highlight('echo "<b>";', 'php'));
        self::assertSame('<span class="hl-keyword">echo</span> <span class="hl-number">1</span>', $extension->highlight('echo 1', 'PHP'));
        self::assertSame('<span class="hl-keyword">return</span>', $extension->highlight(new Markup('return', 'UTF-8'), 'js'));
    }

    public function testAnUnknownLanguageOrNoneGivesTheCodeEscaped(): void
    {
        $extension = new HighlightExtension(Highlighter::default());

        self::assertSame('&lt;b class=&quot;x&quot;&gt;l&#039;a&lt;/b&gt;', $extension->highlight('<b class="x">l\'a</b>', 'cobol'));
        self::assertSame('&lt;b&gt; &#039;', $extension->highlight("<b> '"));
        self::assertSame("\u{FFFD}", $extension->highlight("\xFF"));
        self::assertSame('', $extension->highlight(null, 'php'));
        self::assertSame('', $extension->highlight(null));
    }

    public function testTheLinesAreWrittenAsAsked(): void
    {
        $extension = new HighlightExtension(Highlighter::default());

        self::assertSame(
            '<span class="hl-line hl-line-highlighted"><span class="hl-line-number">1</span><span class="hl-keyword">echo</span></span>'."\n"
            .'<span class="hl-line hl-line-inserted"><span class="hl-line-number">2</span><span class="hl-keyword">echo</span></span>'."\n",
            $extension->highlight("echo\necho // [!code ++]\n", 'php', ['numbers' => true, 'highlight' => '1', 'annotations' => true]),
        );
        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">7</span><span class="hl-keyword">echo</span> <span class="hl-comment">// [!code ++]</span></span>',
            $extension->highlight('echo // [!code ++]', 'php', ['numbers' => 7]),
        );
        self::assertSame(
            '<span class="hl-line"><span class="hl-keyword">echo</span></span>',
            $extension->highlight('echo', 'php', ['numbers' => false]),
        );
        self::assertSame('<span class="hl-line"><span class="hl-keyword">echo</span></span>', $extension->highlight('echo', 'php', []));
        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">3</span><span class="hl-keyword">echo</span></span>',
            $extension->highlight('echo', 'php', LineOptions::numbered(3)),
        );
    }

    public function testAnUnknownLanguageOrNoneIsWrittenLineByLineAsText(): void
    {
        $extension = new HighlightExtension(Highlighter::default());

        self::assertSame(
            '<span class="hl-line"><span class="hl-line-number">1</span>&lt;b&gt;</span>'."\n".'<span class="hl-line hl-line-highlighted"><span class="hl-line-number">2</span>x</span>',
            $extension->highlight("<b>\nx", 'cobol', ['numbers' => true, 'highlight' => '2']),
        );
        self::assertSame('<span class="hl-line">x</span>', $extension->highlight('x', null, []));
        self::assertSame('<span class="hl-line"></span>', $extension->highlight(null, null, []));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedLines(): iterable
    {
        yield 'an unknown key' => [['number' => true], 'The lines of highlight() read "numbers", "highlight" and "annotations", not "number".'];
        yield 'a list' => [[true], 'The lines of highlight() read "numbers", "highlight" and "annotations", not "0".'];
        yield 'numbers as text' => [['numbers' => '1'], 'The "numbers" of highlight() is true, false or the number of the first line.'];
        yield 'ranges as a list' => [['highlight' => [1, 2]], 'The "highlight" of highlight() is a text of line ranges, such as "1,3-5".'];
        yield 'annotations as a number' => [['annotations' => 1], 'The "annotations" of highlight() is true or false.'];
    }

    /**
     * @param array<array-key, mixed> $lines
     */
    #[DataProvider('refusedLines')]
    public function testLinesItDoesNotReadAreRefused(array $lines, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new HighlightExtension(Highlighter::default())->highlight('x', 'php', $lines);
    }

    public function testRangesItCannotReadAreRefused(): void
    {
        $this->expectException(InvalidLineRanges::class);

        new HighlightExtension(Highlighter::default())->highlight('x', 'php', ['highlight' => '3-1']);
    }
}
