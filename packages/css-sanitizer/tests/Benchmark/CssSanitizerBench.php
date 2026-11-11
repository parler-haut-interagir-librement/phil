<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Benchmark;

use Generator;
use Phil\CssSanitizer\CssSanitizer;

/**
 * A style sheet written by a member is sanitized each time it is saved: it is tokenized, parsed, checked against the
 * policy and written back. Sheets of 1, 10 and 100 KB, and sheets made of comments, strings and blocks left open, must
 * cost time in proportion to their length.
 *
 * @BeforeMethods("setUp")
 */
final class CssSanitizerBench
{
    private const array UNITS = [
        'sheet' => ".card > h2:hover, .card p::first-line { color: #333; margin: 0 auto 1em; font: italic 700 1.2rem/1.4 serif; background: url(\"https://example.com/a.png\") no-repeat; }\n@media screen and (min-width: 40em) { .card { display: grid; grid-template-columns: repeat(3, 1fr); } }\n",
        'denied' => ".a { behavior: url(x.htc); -moz-binding: url(y); background: expression(alert(1)); position: fixed; }\n",
        'comments' => '/* a ',
        'strings' => '.a { content: "\\',
        'blocks' => '.a { .b { ',
    ];

    private readonly CssSanitizer $sanitizer;

    private string $css = '';

    public function __construct()
    {
        $this->sanitizer = new CssSanitizer();
    }

    /**
     * @param array{shape: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $unit = self::UNITS[$params['shape']];
        $this->css = str_repeat($unit, max(1, intdiv($params['bytes'], \strlen($unit))));
    }

    /**
     * @ParamProviders("sheets")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchSanitize(): void
    {
        $this->sanitizer->sanitize($this->css);
    }

    /**
     * @return Generator<string, array{shape: string, bytes: int}>
     */
    public function sheets(): Generator
    {
        foreach ([1_000, 10_000, 100_000] as $bytes) {
            yield \sprintf('sheet, %d KB', intdiv($bytes, 1000)) => ['shape' => 'sheet', 'bytes' => $bytes];
        }

        foreach (['denied', 'comments', 'strings', 'blocks'] as $shape) {
            foreach ([10_000, 100_000] as $bytes) {
                yield \sprintf('%s, %d KB', $shape, intdiv($bytes, 1000)) => ['shape' => $shape, 'bytes' => $bytes];
            }
        }
    }
}
