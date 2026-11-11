<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Html\Renderer;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Renderer\CodeRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeRenderer::class)]
#[CoversClass(HtmlRenderer::class)]
final class CodeRendererTest extends TestCase
{
    public function testTheCodeIsEscapedAndItsLanguageNamed(): void
    {
        self::assertSame('<pre class="ujo-code"><code class="language-php">&lt;?php echo &quot;a&quot;;</code></pre>', $this->render(HtmlRenderer::default(), '<?php echo "a";', 'php'));
        self::assertSame('<pre class="ujo-code"><code>x &amp; y</code></pre>', $this->render(HtmlRenderer::default(), 'x & y'));
        self::assertSame('<pre class="ujo-code"><code></code></pre>', HtmlRenderer::default()->render(new Document([new Block('a', 'code', ['code' => 1])])));
    }

    public function testAHighlighterColoursTheCodeOfALanguage(): void
    {
        $calls = [];
        $renderer = HtmlRenderer::default()->withCodeHighlighter(static function (string $code, string $language) use (&$calls): ?string {
            $calls[] = [$code, $language];

            return 'php' === $language ? '<span class="hl-keyword">echo</span> <span class="hl-string">&quot;a&lt;b&quot;</span>;' : null;
        });

        self::assertSame('<pre class="ujo-code"><code class="language-php"><span class="hl-keyword">echo</span> <span class="hl-string">&quot;a&lt;b&quot;</span>;</code></pre>', $this->render($renderer, 'echo "a<b";', 'php'));
        self::assertSame('<pre class="ujo-code"><code class="language-cobol">x</code></pre>', $this->render($renderer, 'x', 'cobol'));
        self::assertSame('<pre class="ujo-code"><code>y</code></pre>', $this->render($renderer, 'y'));
        self::assertSame([['echo "a<b";', 'php'], ['x', 'cobol']], $calls, 'never called without a language');
    }

    public function testHtmlThatIsNotTheCodeIsRefused(): void
    {
        $renderer = HtmlRenderer::default()->withCodeHighlighter(static fn (string $code): string => '<b>'.$code.'</b>');

        self::assertSame('<pre class="ujo-code"><code class="language-php">x</code></pre>', $this->render($renderer, 'x', 'php'));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function highlighted(): iterable
    {
        yield 'text alone' => ['a &lt; b', 'a < b', true];
        yield 'nested spans' => ['<span class="hl-a"><span class="hl-b x_y 1">a</span>b</span>', 'ab', true];
        yield 'named and numeric references' => ['&quot;&#039;&amp;&apos;', '"\'&\'', true];
        yield 'empty' => ['', '', true];
        yield 'another text' => ['a', 'b', false];
        yield 'an unclosed span' => ['<span class="a">x', 'x', false];
        yield 'a closing span first' => ['</span>x', 'x', false];
        yield 'another element' => ['<b>x</b>', 'x', false];
        yield 'another attribute' => ['<span onclick="a">x</span>', 'x', false];
        yield 'a class in capitals' => ['<span class="A">x</span>', 'x', false];
        yield 'an empty class' => ['<span class="">x</span>', 'x', false];
        yield 'a raw closing bracket' => ['a > b', 'a > b', false];
        yield 'a span then text' => ['<span class="a">x</span>y', 'xy', true];
        yield 'a closing span before an opening one' => ['</span><span class="a">x', 'x', false];
        yield 'a span closed twice' => ['<span class="a">x</span></span>', 'x', false];
        yield 'a span cut short' => ['<span class="a"', '', false];
        yield 'a closing tag cut short' => ['<span class="a">x</spa', 'x', false];
    }

    #[DataProvider('highlighted')]
    public function testItChecksTheHighlightedHtml(string $html, string $code, bool $holds): void
    {
        self::assertSame($holds, CodeRenderer::holds($html, $code));
    }

    private function render(HtmlRenderer $renderer, string $code, ?string $language = null): string
    {
        return $renderer->render(new Document([new Block('a', 'code', ['code' => $code, ...null === $language ? [] : ['language' => $language]])]));
    }
}
