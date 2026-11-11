<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit;

use Override;
use Phil\Highlight\Exception\HighlightException;
use Phil\Highlight\Exception\InvalidLanguage;
use Phil\Highlight\Exception\UnknownLanguage;
use Phil\Highlight\Highlighter;
use Phil\Highlight\HtmlFormatter;
use Phil\Highlight\Language;
use Phil\Highlight\LineOptions;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Highlighter::class)]
#[CoversClass(UnknownLanguage::class)]
#[CoversClass(InvalidLanguage::class)]
final class HighlighterTest extends TestCase
{
    public function testTheDefaultHasEveryShippedLanguage(): void
    {
        self::assertSame(
            ['bash', 'c', 'cpp', 'csharp', 'css', 'diff', 'dockerfile', 'go', 'html', 'ini', 'java', 'javascript', 'json', 'markdown', 'php', 'python', 'ruby', 'rust', 'scss', 'sql', 'twig', 'typescript', 'xml', 'yaml'],
            Highlighter::default()->languages(),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function aliases(): iterable
    {
        foreach ([
            'php' => ['php8', 'phtml'], 'javascript' => ['js', 'mjs', 'cjs', 'jsx', 'node'], 'typescript' => ['ts', 'tsx', 'mts', 'cts'],
            'json' => ['jsonc', 'json5', 'geojson', 'webmanifest'], 'html' => ['htm', 'xhtml', 'vue', 'svelte'],
            'xml' => ['svg', 'xsl', 'xslt', 'xsd', 'rss', 'atom', 'plist', 'wsdl', 'xaml', 'mathml'], 'scss' => ['less', 'sass'],
            'sql' => ['mysql', 'postgresql', 'postgres', 'pgsql', 'sqlite', 'plsql', 'tsql', 'mariadb'], 'yaml' => ['yml'],
            'twig' => ['html.twig', 'jinja', 'jinja2', 'nunjucks', 'njk', 'liquid'], 'markdown' => ['md', 'mkd', 'mdx', 'commonmark', 'gfm'],
            'bash' => ['sh', 'shell', 'zsh', 'ksh'], 'python' => ['py', 'py3', 'python3', 'gyp'], 'ruby' => ['rb', 'gemspec', 'podspec', 'rake'],
            'go' => ['golang'], 'rust' => ['rs'], 'java' => ['jsp'], 'c' => ['h'], 'cpp' => ['c++', 'cc', 'cxx', 'hpp', 'hh', 'hxx'],
            'csharp' => ['cs', 'c#', 'dotnet'], 'ini' => ['dosini', 'cfg', 'conf', 'properties', 'env', 'dotenv', 'editorconfig', 'gitconfig'],
            'dockerfile' => ['docker', 'containerfile'], 'diff' => ['patch', 'udiff'], 'css' => [],
        ] as $name => $aliases) {
            yield $name => [$name, $name];

            foreach ($aliases as $alias) {
                yield $alias => [$alias, $name];
            }
        }
    }

    #[DataProvider('aliases')]
    public function testEachLanguageIsFoundByItsNamesInAnyCase(string $asked, string $name): void
    {
        $highlighter = Highlighter::default();

        self::assertTrue($highlighter->supports(strtoupper($asked)));
        self::assertSame($name, $highlighter->language(strtoupper($asked))->name());
        self::assertSame($name, $highlighter->language($asked)->name());
    }

    public function testItHighlightsAsHtml(): void
    {
        $highlighter = Highlighter::default();
        $html = '<span class="hl-keyword">echo</span> <span class="hl-number">1</span><span class="hl-punctuation">;</span>';

        self::assertSame($html, $highlighter->highlight('echo 1;', 'PHP'));
        self::assertSame($html, $highlighter->toHtml('echo 1;', 'php'));
        self::assertSame($html, HtmlFormatter::format($highlighter->tokenize('echo 1;', 'php')));
        self::assertNull($highlighter->highlight('echo 1;', 'cobol'));
        self::assertFalse($highlighter->supports('cobol'));
    }

    public function testItHighlightsLineByLine(): void
    {
        $highlighter = Highlighter::default();
        $options = LineOptions::numbered()->withAnnotations();
        $html = '<span class="hl-line hl-line-inserted"><span class="hl-line-number">1</span><span class="hl-keyword">echo</span> <span class="hl-number">1</span><span class="hl-punctuation">;</span></span>'."\n";

        self::assertSame($html, $highlighter->highlight("echo 1; // [!code ++]\n", 'php', $options));
        self::assertSame($html, $highlighter->toHtml("echo 1; // [!code ++]\n", 'php', $options));
        self::assertSame(HtmlFormatter::formatLines($highlighter->tokenize('echo 1;', 'php'), $options), $highlighter->toHtml('echo 1;', 'php', $options));
        self::assertNull($highlighter->highlight('echo 1;', 'cobol', $options));
    }

    public function testAnUnknownLanguageIsRefusedWithTheKnownOnes(): void
    {
        $highlighter = Highlighter::of($this->language('one', ['uno']), $this->language('two'));

        try {
            $highlighter->toHtml('x', 'Three');
            self::fail('No exception');
        } catch (UnknownLanguage $unknownLanguage) {
            self::assertInstanceOf(HighlightException::class, $unknownLanguage);
            self::assertSame('There is no language named "Three"; the languages are: one, two.', $unknownLanguage->getMessage());
        }
    }

    public function testALanguageIsAddedOrReplaced(): void
    {
        $first = $this->language('one', ['uno']);
        $second = $this->language('one');
        $highlighter = Highlighter::of($first);
        $replaced = $highlighter->with($second);

        self::assertSame($first, $highlighter->language('one'));
        self::assertSame($second, $replaced->language('one'));
        self::assertSame($first, $replaced->language('uno'), 'an alias the new language does not claim stays');
        self::assertSame(['one'], $replaced->languages());
        self::assertSame([], Highlighter::of()->languages());
        self::assertEquals([new Token('x', TokenType::String)], $replaced->tokenize('x', 'ONE'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Js'];
        yield 'space' => ['a b'];
        yield 'slash' => ['a/b'];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'line feed after' => ["js\n"];
    }

    #[DataProvider('invalidNames')]
    public function testANameThatCannotBeAskedForIsRefused(string $name): void
    {
        $this->expectException(InvalidLanguage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" cannot name a language: write lower-case letters, digits and "_ + # . -", 32 at most.', $name));

        Highlighter::of()->with($this->language('ok', [$name]));
    }

    public function testTheLongestAndOddestNamesAreAccepted(): void
    {
        $name = str_repeat('a', 29).'_+#';

        self::assertTrue(Highlighter::of($this->language($name, ['c.d-e', '0']))->supports('C.D-E'));
    }

    /**
     * @param list<string> $aliases
     */
    private function language(string $name, array $aliases = []): Language
    {
        return new readonly class($name, $aliases) implements Language {
            /**
             * @param list<string> $aliases
             */
            public function __construct(private string $name, private array $aliases)
            {
            }

            #[Override]
            public function name(): string
            {
                return $this->name;
            }

            #[Override]
            public function aliases(): array
            {
                return $this->aliases;
            }

            #[Override]
            public function tokenize(string $code): array
            {
                return [new Token($code, TokenType::String)];
            }
        };
    }
}
