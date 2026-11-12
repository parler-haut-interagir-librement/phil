<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Benchmark;

use Generator;
use Phil\SocialCards\Preview\TagReader;

/**
 * The tags of a linked page are read each time its preview is not cached: a head of a few tags, a head of hundreds,
 * and a page of 512 KiB without a head to cut at, which must cost time in proportion to its length.
 *
 * @BeforeMethods("setUp")
 */
final class TagReaderBench
{
    private readonly TagReader $reader;

    private string $html = '';

    public function __construct()
    {
        $this->reader = new TagReader();
    }

    /**
     * @param array{shape: string} $params
     */
    public function setUp(array $params): void
    {
        $tags = '<meta property="og:title" content="A title"><meta property="og:image" content="/card.png"><meta name="description" content="A description of the page">';
        $this->html = match ($params['shape']) {
            'small' => '<!doctype html><html><head><title>T</title>'.$tags.'</head><body>'.str_repeat('<p>Text</p>', 2000).'</body></html>',
            'many' => '<head>'.str_repeat($tags, 150).'</head>',
            default => str_repeat('<div class="a"><span>Text</span></div>', 14_000),
        };
    }

    /**
     * @ParamProviders("pages")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchRead(): void
    {
        $this->reader->read($this->html, 'https://example.com/page');
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function pages(): Generator
    {
        yield 'small head' => ['shape' => 'small'];
        yield 'many tags' => ['shape' => 'many'];
        yield 'no head' => ['shape' => 'large'];
    }
}
