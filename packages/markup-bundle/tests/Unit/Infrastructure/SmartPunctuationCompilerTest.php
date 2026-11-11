<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit\Infrastructure;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Level;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Typography\SmartPunctuationCompiler;
use Phil\Typography\Typographers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

#[CoversClass(SmartPunctuationCompiler::class)]
final class SmartPunctuationCompilerTest extends TestCase
{
    public function testTheFormatsAreFixedInTheDefaultLocale(): void
    {
        $compiler = new SmartPunctuationCompiler(new DefaultCompiler(), $this->typographers(), MarkupFormat::values());

        self::assertSame("<p class=\"ujo-paragraph\">“Yes” – no…</p>\n<pre class=\"ujo-code\"><code>\"a\" -- b...</code></pre>", $compiler->compileFor("\"Yes\" -- no...\n\n```\n\"a\" -- b...\n```", MarkupFormat::Markdown, Level::All));
        self::assertSame('<p class="ujo-paragraph"><b>“a”</b></p>', $compiler->compile('[b]"a"[/b]', MarkupFormat::BBCode));
        self::assertSame('<p>“a”</p>', $compiler->compileFor('<p>"a"</p>', MarkupFormat::Html, Level::All));
    }

    public function testRawHtmlFollowsTheLevel(): void
    {
        $compiler = new SmartPunctuationCompiler(new DefaultCompiler(), $this->typographers(), MarkupFormat::values());

        self::assertSame('<p class="ujo-paragraph"><b>x “a”</b></p>', $compiler->compileFor('<b>x "a"</b>', MarkupFormat::Markdown, Level::All));
        self::assertSame('<p class="ujo-paragraph">&lt;b&gt;x “a”&lt;/b&gt;</p>', $compiler->compileFor('<b>x "a"</b>', MarkupFormat::Markdown, Level::Forum));
        self::assertSame('<p class="ujo-paragraph">&lt;b&gt;x “a”&lt;/b&gt;</p>', $compiler->compile('<b>x "a"</b>', MarkupFormat::Markdown));
    }

    public function testACompilerThatIgnoresLevelsIsAskedWithoutOne(): void
    {
        $inner = new class implements MarkupCompiler {
            public function compile(string $source, MarkupFormat|string $format): string
            {
                return '<p>'.$source.'</p>';
            }
        };
        $compiler = new SmartPunctuationCompiler($inner, $this->typographers(), ['text']);

        self::assertSame('<p>“a”</p>', $compiler->compileFor('"a"', MarkupFormat::Text, Level::Basic));
    }

    public function testOnlyTheFormatsListedAreFixed(): void
    {
        $compiler = new SmartPunctuationCompiler(new DefaultCompiler(), $this->typographers(), ['text']);

        self::assertSame('<p class="ujo-paragraph">&quot;a&quot;</p>', $compiler->compileFor('"a"', MarkupFormat::Markdown, Level::All));
        self::assertSame('<p class="ujo-paragraph">&quot;a&quot;</p>', $compiler->compile('"a"', MarkupFormat::Markdown));
        self::assertSame('<p>“a”</p>', $compiler->compileFor('"a"', MarkupFormat::Text, Level::All));
    }

    public function testTheLocaleIsTheConfiguredOneThenTheCurrentOne(): void
    {
        $switcher = new LocaleSwitcher('fr', []);
        $current = new SmartPunctuationCompiler(new DefaultCompiler(), $this->typographers(), MarkupFormat::values(), null, $switcher);
        $german = new SmartPunctuationCompiler(new DefaultCompiler(), $this->typographers(), MarkupFormat::values(), 'de', $switcher);

        self::assertSame('<p>«&nbsp;a&nbsp;»</p>', $current->compile('"a"', MarkupFormat::Text));
        self::assertSame('<p>„b“</p>', $switcher->runWithLocale('de', static fn (): string => $current->compile('"b"', MarkupFormat::Text)));
        self::assertSame('<p>„a“</p>', $german->compile('"a"', MarkupFormat::Text));
    }

    private function typographers(): Typographers
    {
        return new Typographers('en', [], ['pre', 'code'], false);
    }
}
