<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Benchmark;

use Generator;
use Phil\Markup\Reader\Creole\CreoleReader;
use Phil\Markup\Reader\DokuWiki\DokuWikiReader;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\MediaWiki\MediaWikiReader;
use Phil\Markup\Reader\Text\TextReader;

/**
 * Every format is read into UJO each time a draft is published, from a source an author wrote. Each reader takes a
 * small document, the same document repeated to 100 KB, and 100 KB of delimiters left open: a time that grows ten
 * times from 10 to 100 KB is linear.
 *
 * @BeforeMethods("setUp")
 */
final class ReadersBench
{
    private const array DOCUMENTS = [
        'markdown' => "# Title\n\nSome *emphasis*, **strong**, `code`, [a link](https://example.com/a) and a note[^1].\n\n- one\n- two\n  - nested\n\n| a | b |\n|---|---|\n| c | d |\n\n> A quote\n\n```php\necho 1;\n```\n\n[^1]: The note.\n\n",
        'mediawiki' => "== Title ==\n'''Bold''' and ''italic'' with [[Page|a link]] and a note<ref>Note</ref>.\n* one\n** two\n{|\n| a || b\n|-\n| c || d\n|}\n\n",
        'dokuwiki' => "====== Title ======\n**Bold** and //italic// with [[page|a link]] and a note((Note)).\n  * one\n    * two\n^ a ^ b ^\n| c | d |\n\n",
        'creole' => "= Title =\n**Bold** and //italic// with [[Page|a link]].\n* one\n** two\n|=a|=b|\n|c|d|\n\n",
        'html' => '<h2>Title</h2><p>Some <b>bold</b>, <i>italic</i> and <a href="https://example.com/a">a link</a>.</p><ul><li>one<ul><li>two</li></ul></li></ul><table><tr><th>a</th><th>b</th></tr><tr><td>c</td><td>d</td></tr></table><blockquote><p>A quote</p></blockquote>',
        'text' => "A line of text with an address https://example.com/a in it.\n\nAnother paragraph.\n\n",
    ];

    private const array HOSTILE = [
        'markdown' => '*a _b [c ',
        'mediawiki' => "'' [[a {{b ",
        'dokuwiki' => '**a //b [[c ',
        'creole' => '**a //b [[c ',
        'html' => '<b><i>',
        'text' => 'https://e.x/a ',
    ];

    private MarkupReader $reader;

    private string $source = '';

    public function __construct()
    {
        $this->reader = new TextReader();
    }

    /**
     * @param array{format: string, shape: string, bytes: int} $params
     */
    public function setUp(array $params): void
    {
        $this->reader = match ($params['format']) {
            'markdown' => new MarkdownReader(),
            'mediawiki' => new MediaWikiReader(),
            'dokuwiki' => new DokuWikiReader(),
            'creole' => new CreoleReader(),
            'html' => new HtmlReader(),
            default => new TextReader(),
        };
        $unit = 'hostile' === $params['shape'] ? self::HOSTILE[$params['format']] : self::DOCUMENTS[$params['format']];
        $this->source = str_repeat($unit, max(1, intdiv($params['bytes'], \strlen($unit))));
    }

    /**
     * @ParamProviders("small")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchReadSmall(): void
    {
        $this->reader->read($this->source);
    }

    /**
     * @ParamProviders("large")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadLarge(): void
    {
        $this->reader->read($this->source);
    }

    /**
     * @return Generator<string, array{format: string, shape: string, bytes: int}>
     */
    public function small(): Generator
    {
        foreach (array_keys(self::DOCUMENTS) as $format) {
            yield $format.', one document' => ['format' => $format, 'shape' => 'document', 'bytes' => 0];
        }
    }

    /**
     * @return Generator<string, array{format: string, shape: string, bytes: int}>
     */
    public function large(): Generator
    {
        foreach (array_keys(self::DOCUMENTS) as $format) {
            foreach (['document', 'hostile'] as $shape) {
                foreach ([10_000, 100_000] as $bytes) {
                    yield \sprintf('%s, %s, %d KB', $format, $shape, intdiv($bytes, 1000)) => ['format' => $format, 'shape' => $shape, 'bytes' => $bytes];
                }
            }
        }
    }
}
