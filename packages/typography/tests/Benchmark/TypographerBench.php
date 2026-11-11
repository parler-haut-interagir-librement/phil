<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Benchmark;

use Generator;
use Phil\Typography\Typographer;

/**
 * The typographer runs on the HTML of each page published, in the language of the page: every fixer reads every text
 * of the fragment. Fragments of 1, 10 and 100 KB in English and French, with hyphenation, and a fragment of quotes and
 * dots left open must cost time in proportion to their length.
 *
 * @BeforeMethods("setUp")
 */
final class TypographerBench
{
    private Typographer $typographer;

    private string $html = '';

    public function __construct()
    {
        $this->typographer = Typographer::for('en');
    }

    /**
     * @param array{locale: string, shape: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $this->typographer = Typographer::for($params['locale']);
        $unit = match ($params['shape']) {
            'hostile' => '"\'... -- (c) ',
            default => '<p>"Hello," she said -- it was 1990 -- "the price is 5 x 10 cm... isn\'t it?" <em>Typography</em> matters (c) 2026: see <a href="https://example.com/a">this</a>!</p><pre>"keep" -- as is</pre>',
        };
        $this->html = str_repeat($unit, max(1, intdiv($params['bytes'], \strlen($unit))));
    }

    /**
     * @ParamProviders("fragments")
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchFix(): void
    {
        $this->typographer->fix($this->html);
    }

    /**
     * @ParamProviders("texts")
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchFixText(): void
    {
        $this->typographer->fixText($this->html);
    }

    /**
     * @ParamProviders("french")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchBuildFrench(): void
    {
        Typographer::for('fr');
    }

    /**
     * @return Generator<string, array{locale: string, shape: string, bytes: int}>
     */
    public function fragments(): Generator
    {
        foreach (['en', 'fr'] as $locale) {
            foreach ([1_000, 10_000, 100_000] as $bytes) {
                yield \sprintf('%s, %d KB', $locale, intdiv($bytes, 1000)) => ['locale' => $locale, 'shape' => 'page', 'bytes' => $bytes];
            }
        }

        yield 'fr, hostile, 10 KB' => ['locale' => 'fr', 'shape' => 'hostile', 'bytes' => 10_000];
        yield 'fr, hostile, 100 KB' => ['locale' => 'fr', 'shape' => 'hostile', 'bytes' => 100_000];
    }

    /**
     * @return Generator<string, array{locale: string, shape: string, bytes: int}>
     */
    public function french(): Generator
    {
        yield 'fr' => ['locale' => 'fr', 'shape' => 'page', 'bytes' => 0];
    }

    /**
     * @return Generator<string, array{locale: string, shape: string, bytes: int}>
     */
    public function texts(): Generator
    {
        yield 'en, hostile, 10 KB' => ['locale' => 'en', 'shape' => 'hostile', 'bytes' => 10_000];
        yield 'en, hostile, 100 KB' => ['locale' => 'en', 'shape' => 'hostile', 'bytes' => 100_000];
    }
}
