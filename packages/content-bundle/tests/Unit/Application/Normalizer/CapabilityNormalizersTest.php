<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Normalizer;

use Phil\ContentBundle\Application\Normalizer\BodyNormalizer;
use Phil\ContentBundle\Application\Normalizer\ExcerptNormalizer;
use Phil\ContentBundle\Application\Normalizer\LinkNormalizer;
use Phil\ContentBundle\Application\Normalizer\RoutableNormalizer;
use Phil\ContentBundle\Application\Normalizer\SeoNormalizer;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\MarkingHtmlPurifier;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\MarkupFormat;
use Phil\Slug\Slug;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeoNormalizer::class)]
#[CoversClass(ExcerptNormalizer::class)]
#[CoversClass(LinkNormalizer::class)]
#[CoversClass(RoutableNormalizer::class)]
#[CoversClass(BodyNormalizer::class)]
final class CapabilityNormalizersTest extends TestCase
{
    public function testSeoIsNormalized(): void
    {
        $row = $this->row();
        $row->setSeoTitle('Bonjour');
        $row->setSeoDescription('Une description');
        $row->setSeoKeywords(['larp', 'events']);
        $row->setSeoCanonicalUrl(Url::fromString('https://example.org/fr'));
        $row->setSeoNoIndex(true);
        $row->setSeoNoFollow(false);

        self::assertSame([
            'seoTitle' => 'Bonjour',
            'seoDescription' => 'Une description',
            'seoKeywords' => ['larp', 'events'],
            'seoCanonicalUrl' => 'https://example.org/fr',
            'seoNoIndex' => true,
            'seoNoFollow' => false,
            'seoHideInSitemap' => false,
        ], new SeoNormalizer()->normalize($row));
    }

    /**
     * A directive no row set reads as false: a renderer never has to guess what null means.
     */
    public function testAnEmptySeoReadsAsNoDirective(): void
    {
        self::assertSame([
            'seoTitle' => null,
            'seoDescription' => null,
            'seoKeywords' => [],
            'seoCanonicalUrl' => null,
            'seoNoIndex' => false,
            'seoNoFollow' => false,
            'seoHideInSitemap' => false,
        ], new SeoNormalizer()->normalize($this->row()));
    }

    public function testADirectiveSetToTrueReadsAsTrue(): void
    {
        $row = $this->row();
        $row->setSeoNoFollow(true);
        $row->setSeoHideInSitemap(true);

        $normalized = new SeoNormalizer()->normalize($row);

        self::assertFalse($normalized['seoNoIndex']);
        self::assertTrue($normalized['seoNoFollow']);
        self::assertTrue($normalized['seoHideInSitemap']);
    }

    public function testTheExcerptIsNormalized(): void
    {
        $row = $this->row();
        $row->setExcerptTitle('Bonjour');
        $row->setExcerptDescription('Une description');
        $row->setExcerptImage('media:42');

        self::assertSame(
            ['excerptTitle' => 'Bonjour', 'excerptDescription' => 'Une description', 'excerptImage' => 'media:42'],
            new ExcerptNormalizer()->normalize($row),
        );
    }

    public function testALinkHasOneShapeForBothKindsOfTarget(): void
    {
        $row = $this->row();
        self::assertSame(['link' => null], new LinkNormalizer()->normalize($row));

        $row->setLink(Link::toContent('article', '42', Locale::fromString('fr_CA')));
        self::assertSame(
            ['link' => ['resourceKey' => 'article', 'resourceId' => '42', 'locale' => 'fr_CA', 'url' => null]],
            new LinkNormalizer()->normalize($row),
        );

        $row->setLink(Link::toUrl(Url::fromString('https://example.org/')));
        self::assertSame(
            ['link' => ['resourceKey' => null, 'resourceId' => null, 'locale' => null, 'url' => 'https://example.org/']],
            new LinkNormalizer()->normalize($row),
        );
    }

    public function testTheSlugIsNormalized(): void
    {
        $row = $this->row();
        self::assertSame(['slug' => null], new RoutableNormalizer()->normalize($row));

        $row->setSlug(Slug::fromString('my-first-article'));
        self::assertSame(['slug' => 'my-first-article'], new RoutableNormalizer()->normalize($row));
    }

    /**
     * The stored HTML is purified again on its way out, whoever wrote it.
     */
    public function testTheBodyHtmlIsPurifiedOnRead(): void
    {
        $row = $this->row();
        $row->setBody(Body::of('<p>Bonjour</p><script>alert(1)</script>', MarkupFormat::Html, '<p>Bonjour</p><script>alert(1)</script>'));

        self::assertSame([
            'bodySource' => '<p>Bonjour</p><script>alert(1)</script>',
            'bodyFormat' => 'html',
            'bodyHtml' => '<purified><p>Bonjour</p><script>alert(1)</script></purified>',
        ], new BodyNormalizer(new MarkingHtmlPurifier())->normalize($row));
    }

    public function testARowWithoutABodyGivesNullValues(): void
    {
        self::assertSame(
            ['bodySource' => null, 'bodyFormat' => null, 'bodyHtml' => null],
            new BodyNormalizer(new MarkingHtmlPurifier())->normalize($this->row()),
        );
    }

    #[DataProvider('normalizers')]
    public function testARowWithoutTheCapabilityGivesNothing(Normalizer $normalizer): void
    {
        self::assertSame([], $normalizer->normalize(
            new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Live, Version::first())),
        ));
    }

    /**
     * @return iterable<string, array{Normalizer}>
     */
    public static function normalizers(): iterable
    {
        yield 'SEO' => [new SeoNormalizer()];
        yield 'excerpt' => [new ExcerptNormalizer()];
        yield 'link' => [new LinkNormalizer()];
        yield 'routable' => [new RoutableNormalizer()];
        yield 'body' => [new BodyNormalizer(new MarkingHtmlPurifier())];
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first()));
    }
}
