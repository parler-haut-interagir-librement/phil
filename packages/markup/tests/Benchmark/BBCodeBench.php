<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Benchmark;

use Generator;
use Phil\Markup\Reader\BBCode\BBCodeReader;

/**
 * BBCode is read each time a forum post is saved, from a source a member wrote: tags nested deep, left open or closed
 * without being opened must cost time in proportion to the length of the source, never to its square. Each shape is
 * read at 25, 50 and 100 KB: a time that doubles with the size is linear.
 *
 * @BeforeMethods("setUp")
 */
final class BBCodeBench
{
    private string $source = '';

    /**
     * @param array{shape: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $unit = match ($params['shape']) {
            // A quote, styled words, a link and a list.
            'post' => "[quote=Ada]Hello [b]there[/b], see [url]https://example.com/a[/url].[/quote]\n[i]Yes[/i], [u]and[/u]:\n[list][*]one[*]two[/list]\n\n",
            // Tags opened inside each other, never closed.
            'nested' => '[b][i]',
            // Blocks left open, then closing tags that no open tag matches.
            'unmatched' => '[quote][/s]',
            // Brackets that open no tag.
            'brackets' => '[',
            // Items, rows and cells left open.
            'structures' => '[list][*]a[table][tr][td]b',
            // Headings of the same text and footnotes, each a search a naive reader repeats.
            'references' => '[h2]a[/h2]x[footnote]y[/footnote]',
            default => ' ',
        };

        $this->source = str_repeat($unit, intdiv($params['bytes'], \strlen($unit)));
    }

    /**
     * @ParamProviders("posts")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadPost(): void
    {
        new BBCodeReader()->read($this->source);
    }

    /**
     * @ParamProviders("hostile")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadHostile(): void
    {
        new BBCodeReader()->read($this->source);
    }

    /**
     * @return Generator<string, array{shape: string, bytes: int}>
     */
    public function posts(): Generator
    {
        yield from $this->sizes('post');
    }

    /**
     * @return Generator<string, array{shape: string, bytes: int}>
     */
    public function hostile(): Generator
    {
        foreach (['nested', 'unmatched', 'brackets', 'structures', 'references'] as $shape) {
            yield from $this->sizes($shape);
        }
    }

    /**
     * @return Generator<string, array{shape: string, bytes: int}>
     */
    private function sizes(string $shape): Generator
    {
        foreach ([25_000, 50_000, 100_000] as $bytes) {
            yield \sprintf('%s, %d KB', $shape, intdiv($bytes, 1000)) => ['shape' => $shape, 'bytes' => $bytes];
        }
    }
}
