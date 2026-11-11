<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Phil\Typography\Fixer\CurlyQuote;
use Phil\Typography\Fixer\Ellipsis;
use Phil\Typography\Fixer\Fixer;
use Phil\Typography\Fixer\SmartQuotes;
use Phil\Typography\Html\Blocks;
use Phil\Typography\Html\Collector;
use Phil\Typography\Html\Serializer;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Locale;
use Phil\Typography\Text\Run;
use Phil\Typography\Typographer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Typographer::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Serializer::class)]
#[CoversClass(Collector::class)]
final class TypographerTest extends TestCase
{
    private const string NB = "\u{A0}";

    private const string NNB = "\u{202F}";

    public function testOnlyTheTextChanges(): void
    {
        $typographer = new Typographer([new SmartQuotes(), new CurlyQuote()]);
        $html = '<p class="a" title="&quot;x&quot;">"It\'s" <a href="/?a=1&amp;b=2">here</a> &amp; <!-- "c" --> <img alt="\'"></p>';

        self::assertSame('<p class="a" title="&quot;x&quot;">“It’s” <a href="/?a=1&amp;b=2">here</a> &amp; <!-- "c" --> <img alt="\'"></p>', $typographer->fix($html));
    }

    public function testBracketsInAttributeValuesAreEscaped(): void
    {
        $typographer = new Typographer([new SmartQuotes()]);

        self::assertSame('<abbr title="a&gt;b&lt;c">“x”</abbr><style>a>b {}</style>', $typographer->fix('<abbr title="a>b&lt;c">"x"</abbr><style>a>b {}</style>'));
        self::assertSame('<p title="'."\u{E000}".'&gt;'."\u{E000}".'" lang="x">'."\u{E000}\u{E000}".'l “a”</p>', $typographer->fix('<p title="&#xE000;>&#xE000;" lang="x">&#xE000;&#xE000;l "a"</p>'));
        self::assertSame('<!DOCTYPE html><html><head></head><body><p title="a&gt;b">“x”</p></body></html>', $typographer->fixDocument('<!doctype html><p title="a>b">"x"</p>'));
        self::assertSame('<p title="a">“x”</p>', $typographer->fix('<p title="a">"x"</p>'));
    }

    public function testQuotesSpanInlineElementsButNotBlocks(): void
    {
        $typographer = new Typographer([new SmartQuotes()]);

        self::assertSame('<p>“<em>word</em>”</p>', $typographer->fix('<p>"<em>word</em>"</p>'));
        self::assertSame('<p>a”</p><p>“b</p>', $typographer->fix('<p>a"</p><p>"b</p>'));
        self::assertSame('<p>a ”<br>“b</p>', $typographer->fix('<p>a "<br>"b</p>'));
        self::assertSame('<ul><li>“a”</li><li>“b”</li></ul>', $typographer->fix('<ul><li>"a"</li><li>"b"</li></ul>'));
    }

    public function testATagEscapedAsTextOpensTheQuoteAfterIt(): void
    {
        $typographer = new Typographer([new SmartQuotes()]);

        self::assertSame('<p>&gt;“a” &lt;b&gt;“c”&lt;/b&gt;</p>', $typographer->fix('<p>&gt;"a" &lt;b&gt;"c"&lt;/b&gt;</p>'));
    }

    public function testEveryBoundaryEndsARun(): void
    {
        $typographer = new Typographer([new SmartQuotes()]);

        foreach (Blocks::BOUNDARIES as $name) {
            if (\in_array($name, ['body', 'head', 'html', 'title', 'br', 'hr', 'table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'caption', 'option', 'dd', 'dt', 'li', 'p'], true)) {
                continue;
            }

            self::assertSame('x ”<'.$name.'>“y</'.$name.'>', $typographer->fix('x "<'.$name.'>"y</'.$name.'>'), $name);
        }

        self::assertSame('<table><tbody><tr><td>a ”</td><td>“b</td></tr></tbody></table>', $typographer->fix('<table><tr><td>a "</td><td>"b</td></tr></table>'));
        self::assertSame('<dl><dt>a ”</dt><dd>“b</dd></dl>', $typographer->fix('<dl><dt>a "</dt><dd>"b</dd></dl>'));
        self::assertSame('a ”<hr>“b', $typographer->fix('a "<hr>"b'));
        self::assertSame('<select><option>a ”</option><option>“b</option></select>', $typographer->fix('<select><option>a "</option><option>"b</option></select>'));
        self::assertSame('<span>a </span><span>“b”</span>', $typographer->fix('<span>a </span><span>"b"</span>'));
        self::assertSame('<span>a</span><span>”b”</span>', $typographer->fix('<span>a</span><span>"b"</span>'));
    }

    public function testProtectedElementsAreLeftButSeen(): void
    {
        $typographer = new Typographer([new SmartQuotes(), new CurlyQuote(), new Ellipsis()]);

        self::assertSame('<p>“<code>x"y\'z...</code>” it’s…</p><pre>"a"...</pre><script>if (a<b) { s = "\'"; }</script><style>a::after { content: "..."; }</style><kbd>"k"</kbd><textarea>"t"</textarea>', $typographer->fix('<p>"<code>x"y\'z...</code>" it\'s...</p><pre>"a"...</pre><script>if (a<b) { s = "\'"; }</script><style>a::after { content: "..."; }</style><kbd>"k"</kbd><textarea>"t"</textarea>'));
        self::assertSame(['pre', 'code', 'kbd', 'script', 'style', 'textarea'], Typographer::PROTECTED_TAGS);
    }

    public function testTheTextOfAProtectedElementIsContext(): void
    {
        self::assertSame('<p>x <code>y</code>”z”</p>', new Typographer([new SmartQuotes()])->fix('<p>x <code>y</code>"z"</p>'));
    }

    public function testARunEndsAfterABlock(): void
    {
        self::assertSame('<div>a ”</div>“b', new Typographer([new SmartQuotes()])->fix('<div>a "</div>"b'));
    }

    public function testTheProtectedElementsAreConfigurable(): void
    {
        $typographer = new Typographer([new Ellipsis()], ['SAMP', 'var']);

        self::assertSame('<samp>...</samp><var>...</var><code>…</code><pre>…</pre>', $typographer->fix('<samp>...</samp><var>...</var><code>...</code><pre>...</pre>'));
        self::assertSame('<samp>...</samp>', $typographer->fix('<SAMP>...</SAMP>'));
    }

    public function testAWholeDocument(): void
    {
        $typographer = new Typographer([new SmartQuotes(), new Ellipsis()]);

        self::assertSame('<!DOCTYPE html><html lang="en"><head><title>“Title”…</title><style>p::before { content: "..."; }</style></head><body><p>“x”</p></body></html>', $typographer->fixDocument('<!doctype html><html lang="en"><head><title>"Title"...</title><style>p::before { content: "..."; }</style></head><body><p>"x"</p></body></html>'));
    }

    public function testPlainText(): void
    {
        $typographer = Typographer::for('fr_FR', false);

        self::assertSame('«'.self::NB.'Bonjour'.self::NB.'», dit-il'.self::NB.': l’été arrive'.self::NNB.'! <b>', $typographer->fixText('"Bonjour" , dit-il : l\'été arrive ! <b>'));
        self::assertSame('a?…', new Typographer([new Ellipsis()])->fixText("a\xFF..."));
        self::assertSame('', new Typographer([new Ellipsis()])->fixText(''));
    }

    public function testFixersRunInOrder(): void
    {
        $first = new class implements Fixer {
            public function fix(Run $run): Run
            {
                return $run->map(static fn (string $text): string => $text.'1');
            }
        };
        $second = new class implements Fixer {
            public function fix(Run $run): Run
            {
                return $run->map(static fn (string $text): string => $text.'2');
            }
        };

        self::assertSame(['a12'], new Typographer([$first, $second])->fixRun(Run::of('a'))->texts);
        self::assertSame('<p>a12</p>', new Typographer([$first, $second])->fix('<p>a</p>'));
        self::assertSame('<p>a</p>', new Typographer([])->fix('<p>a</p>'));
    }

    public function testTheTypographerOfALocale(): void
    {
        $html = '<p>"Silbentrennungsalgorithmus" - ja...</p>';

        self::assertSame("<p>„Sil\u{AD}ben\u{AD}tren\u{AD}nungs\u{AD}al\u{AD}go\u{AD}rith\u{AD}mus“ – ja…</p>", Typographer::for('de_DE')->fix($html));
        self::assertSame("<p>„Sil\u{AD}ben\u{AD}tren\u{AD}nungs\u{AD}al\u{AD}go\u{AD}rith\u{AD}mus“ – ja…</p>", Typographer::for(Locale::parse('de_DE'), true, new Dictionary())->fix($html));
        self::assertSame('<p>„Silbentrennungsalgorithmus“ – ja…</p>', Typographer::for('de_DE', false)->fix($html));
        self::assertSame('<code>„x“</code>', Typographer::for('de_DE', false, null, [])->fix('<code>"x"</code>'));
    }
}
