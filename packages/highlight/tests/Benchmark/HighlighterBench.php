<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Benchmark;

use Generator;
use Phil\Highlight\Highlighter;

/**
 * Each code block of a page is highlighted on the server when it is published. Sources of 1, 10 and 100 KB in the
 * common languages, and sources made of strings and comments left open, must cost time in proportion to their length.
 *
 * @BeforeMethods("setUp")
 */
final class HighlighterBench
{
    private const array SOURCES = [
        'php' => "<?php\n/** Doc */\nfinal class A { public function b(int \$c): string { return \"x{\$c}\" . 'y'; } }\n// comment\n",
        'javascript' => "const a = (b) => `x\${b}` + 'y'; /* c */ function d() { return /re+/g.test(a); }\n",
        'html' => "<div class=\"a\"><script>let x = 1;</script><style>.a { color: red; }</style><p>Text &amp; more</p></div>\n",
        'css' => ".a > .b:hover { color: #fff; margin: 0 auto; } @media (min-width: 10em) { .c { display: none; } }\n",
        'yaml' => "key: value\nlist:\n  - one # comment\n  - 'two'\nblock: |\n  text\n",
        'twig' => "{% for a in b %}<li>{{ a.name|upper }}</li>{# note #}{% endfor %}\n",
        'sql' => "SELECT a, b FROM t WHERE c = 'x' AND d > 3 -- note\nORDER BY a;\n",
    ];

    private const array HOSTILE = [
        'php' => '<?php /* "\' ',
        'javascript' => '`${"\'/* ',
        'html' => '<a b="<!-- ',
        'css' => '/* "url( ',
        'yaml' => "- '\"# |\n",
        'twig' => '{{ {% {# ',
        'sql' => "'-- /* \"",
    ];

    private readonly Highlighter $highlighter;

    private string $code = '';

    private string $language = '';

    public function __construct()
    {
        $this->highlighter = Highlighter::default();
    }

    /**
     * @param array{language: string, shape: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $this->language = $params['language'];
        $unit = 'hostile' === $params['shape'] ? self::HOSTILE[$this->language] : self::SOURCES[$this->language];
        $this->code = str_repeat($unit, max(1, intdiv($params['bytes'], \strlen($unit))));
    }

    /**
     * @ParamProviders("sources")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchToHtml(): void
    {
        $this->highlighter->toHtml($this->code, $this->language);
    }

    /**
     * @ParamProviders("languages")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchDefault(): void
    {
        Highlighter::default();
    }

    /**
     * @return Generator<string, array{language: string, shape: string, bytes: int}>
     */
    public function languages(): Generator
    {
        yield 'every language' => ['language' => 'php', 'shape' => 'source', 'bytes' => 0];
    }

    /**
     * @return Generator<string, array{language: string, shape: string, bytes: int}>
     */
    public function sources(): Generator
    {
        foreach (array_keys(self::SOURCES) as $language) {
            foreach ([1_000, 10_000, 100_000] as $bytes) {
                yield \sprintf('%s, %d KB', $language, intdiv($bytes, 1000)) => ['language' => $language, 'shape' => 'source', 'bytes' => $bytes];
            }

            yield \sprintf('%s, hostile, 10 KB', $language) => ['language' => $language, 'shape' => 'hostile', 'bytes' => 10_000];
            yield \sprintf('%s, hostile, 100 KB', $language) => ['language' => $language, 'shape' => 'hostile', 'bytes' => 100_000];
        }
    }
}
