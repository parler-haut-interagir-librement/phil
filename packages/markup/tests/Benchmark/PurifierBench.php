<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Benchmark;

use Generator;
use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;

/**
 * The purification alone, without the reading: an HTML stored once compiled is purified again at each render under
 * the level of the page. Each purifier, at each level, on a comment, a long post and an article; the ammonia purifier
 * only where its extension is loaded, as in the `bench-ext` image of `phil/dev-tools`, and on every length, the short
 * ones included that it gives to the sanitizer by default.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(20)
 *
 * @Iterations(5)
 */
final class PurifierBench
{
    private const string MARKDOWN = "## A title\n\nSome *emphasis*, **strong**, `code`, [a link](https://example.com/a \"A title\") and ![an image](https://example.com/a.png).\n\n- one\n- two\n  - nested\n\n| a | b |\n|---|---|\n| c | d |\n\n> A quote with <span onclick=\"x\">a handler</span>.\n\n```php\necho 1;\n```\n\n";

    private HtmlPurifier $purifier;

    private string $html = '';

    public function __construct()
    {
        $this->purifier = SanitizerPurifier::defaultPolicies();
    }

    /**
     * @param array{purifier: string, level: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $this->purifier = 'ammonia' === $params['purifier'] ? AmmoniaPurifier::defaultPolicies(minimumBytes: 0) : SanitizerPurifier::defaultPolicies();
        $unit = new DefaultCompiler()->compile(self::MARKDOWN, MarkupFormat::Markdown);
        $this->html = substr(str_repeat($unit, intdiv($params['bytes'], \strlen($unit)) + 1), 0, $params['bytes']);
    }

    /**
     * @ParamProviders("cases")
     *
     * @param array{purifier: string, level: string, bytes: int} $params
     */
    public function benchPurify(array $params): void
    {
        $this->purifier->purify($this->html, Level::from($params['level']));
    }

    /**
     * @return Generator<string, array{purifier: string, level: string, bytes: int}>
     */
    public function cases(): Generator
    {
        $purifiers = AmmoniaPurifier::isAvailable() ? ['symfony', 'ammonia'] : ['symfony'];

        foreach ([300, 12_000, 100_000] as $bytes) {
            foreach (Level::cases() as $level) {
                foreach ($purifiers as $purifier) {
                    yield \sprintf('%s %s %d', $purifier, $level->value, $bytes) => ['purifier' => $purifier, 'level' => $level->value, 'bytes' => $bytes];
                }
            }
        }
    }
}
