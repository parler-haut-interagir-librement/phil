<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Benchmark;

use Generator;
use LogicException;
use Phil\ContentBundle\Application\Normalizer\BodyNormalizer;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\MarkupFormat;

/**
 * The body of a content is purified again through `symfony/html-sanitizer` each time the content is normalized, which
 * is every read of a page, a preview or an API response: the cost follows the length of the stored HTML.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(20)
 *
 * @Iterations(5)
 */
final readonly class BodyNormalizerBench
{
    private const string PARAGRAPH = '<h2>A heading</h2><p>A paragraph with <strong>strong</strong> and <em>emphasised</em> words, <a href="https://example.com/page">a link</a> and an image <img src="https://example.com/a.png" alt="An image">.</p><ul><li>One</li><li>Two</li></ul>';

    private BodyNormalizer $normalizer;

    private ArticleDimension $content;

    public function __construct()
    {
        $this->normalizer = new BodyNormalizer(SanitizerPurifier::defaultPolicies());
        $content = new Article()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first()));

        if (!$content instanceof ArticleDimension) {
            throw new LogicException('An article has rows of ArticleDimension.');
        }

        $this->content = $content;
    }

    /**
     * @param array{paragraphs: int} $params
     */
    public function setUp(array $params): void
    {
        $html = str_repeat(self::PARAGRAPH, $params['paragraphs']);
        $this->content->setBody(Body::of($html, MarkupFormat::Html, $html));
    }

    /**
     * @ParamProviders("bodies")
     */
    public function benchNormalize(): void
    {
        $this->normalizer->normalize($this->content);
    }

    /**
     * @return Generator<string, array{paragraphs: int}>
     */
    public function bodies(): Generator
    {
        yield 'short, 1 paragraph' => ['paragraphs' => 1];
        yield 'article, 20 paragraphs' => ['paragraphs' => 20];
        yield 'long read, 200 paragraphs' => ['paragraphs' => 200];
    }
}
