<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Benchmark;

use Generator;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension;

/**
 * The Twig filters compile and purify, or read into UJO, on every render, nothing cached: a forum thread or a list page calls them once
 * per post, so their cost is multiplied by the posts a page shows.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(20)
 *
 * @Iterations(5)
 */
final class MarkupExtensionBench
{
    private const string MARKDOWN = "Hello **there**, see [the rules](https://example.com/rules).\n\n- one\n- two *and* three\n\n> A quote from `Ada`.\n\n";

    private const string BBCODE = "[quote=Ada]Hello [b]there[/b], see [url]https://example.com/rules[/url].[/quote]\n[list][*]one[*]two [i]and[/i] three[/list]\n";

    private readonly Markup $markup;

    private readonly MarkupExtension $extension;

    private string $source = '';

    private string $html = '';

    public function __construct()
    {
        $purifier = SanitizerPurifier::defaultPolicies();
        $this->markup = new Markup(new DefaultCompiler(), $purifier);
        $formats = new MarkupFormats(MarkupFormat::values(), 'markdown', Level::Forum);
        $this->extension = new MarkupExtension($this->markup, $purifier, $formats, new UjoConverter(new DefaultCompiler(), $formats));
    }

    /**
     * @param array{format: string, paragraphs: int} $params
     */
    public function setUp(array $params): void
    {
        $this->source = str_repeat('bbcode' === $params['format'] ? self::BBCODE : self::MARKDOWN, $params['paragraphs']);
        $this->html = $this->markup->toHtml($this->source, MarkupFormat::from($params['format']), Level::Forum);
    }

    /**
     * `{{ source|markup_html(format, 'forum') }}`: compile, then purify.
     *
     * @ParamProviders("posts")
     *
     * @param array{format: string, paragraphs: int} $params
     */
    public function benchMarkup(array $params): void
    {
        $this->extension->markup($this->source, $params['format'], 'forum');
    }

    /**
     * `{{ post.body|markup_purify }}`: the stored HTML purified again.
     *
     * @ParamProviders("posts")
     */
    public function benchPurify(): void
    {
        $this->extension->purify($this->html);
    }

    /**
     * `{{ source|markup_ujo(format) }}`: read into UJO, then written as JSON.
     *
     * @ParamProviders("posts")
     *
     * @param array{format: string, paragraphs: int} $params
     */
    public function benchUjo(array $params): void
    {
        $this->extension->ujo($this->source, $params['format']);
    }

    /**
     * @return Generator<string, array{format: string, paragraphs: int}>
     */
    public function posts(): Generator
    {
        foreach (['markdown', 'bbcode'] as $format) {
            foreach (['comment' => 1, 'post' => 10, 'long post' => 100] as $size => $paragraphs) {
                yield $format.', '.$size => ['format' => $format, 'paragraphs' => $paragraphs];
            }
        }
    }
}
