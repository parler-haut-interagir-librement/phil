<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Merger;

use Phil\ContentBundle\Application\Merger\BodyMerger;
use Phil\ContentBundle\Application\Merger\ExcerptMerger;
use Phil\ContentBundle\Application\Merger\LinkMerger;
use Phil\ContentBundle\Application\Merger\RoutableMerger;
use Phil\ContentBundle\Application\Merger\SeoMerger;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\MarkupFormat;
use Phil\Slug\Slug;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The mergers of the SEO, excerpt, link, routable and body capabilities: a value the source sets wins, a value
 * it leaves unset keeps the target's.
 */
#[CoversClass(SeoMerger::class)]
#[CoversClass(ExcerptMerger::class)]
#[CoversClass(LinkMerger::class)]
#[CoversClass(RoutableMerger::class)]
#[CoversClass(BodyMerger::class)]
final class CapabilityMergersTest extends TestCase
{
    public function testEverySeoValueTheSourceSetsWins(): void
    {
        $target = $this->row();
        $target->setSeoTitle('shared');
        $target->setSeoDescription('shared');
        $target->setSeoKeywords(['shared']);
        $target->setSeoCanonicalUrl(Url::fromString('https://example.org/shared'));
        $target->setSeoNoIndex(true);
        $target->setSeoNoFollow(true);
        $target->setSeoHideInSitemap(true);

        $source = $this->row();
        $source->setSeoTitle('Bonjour');
        $source->setSeoDescription('Une description');
        $source->setSeoKeywords(['larp']);
        $source->setSeoCanonicalUrl(Url::fromString('https://example.org/fr'));
        $source->setSeoNoIndex(false);
        $source->setSeoNoFollow(false);
        $source->setSeoHideInSitemap(false);

        new SeoMerger()->merge($target, $source);

        self::assertSame('Bonjour', $target->getSeoTitle());
        self::assertSame('Une description', $target->getSeoDescription());
        self::assertSame(['larp'], $target->getSeoKeywords());
        self::assertSame('https://example.org/fr', $target->getSeoCanonicalUrl()?->toString());
        self::assertFalse($target->getSeoNoIndex(), 'A localized false lifts a shared true.');
        self::assertFalse($target->getSeoNoFollow());
        self::assertFalse($target->getSeoHideInSitemap());
    }

    public function testASeoSourceThatSetsNothingKeepsTheTarget(): void
    {
        $target = $this->row();
        $target->setSeoTitle('shared');
        $target->setSeoDescription('shared');
        $target->setSeoKeywords(['shared']);
        $target->setSeoCanonicalUrl(Url::fromString('https://example.org/shared'));
        $target->setSeoNoIndex(true);
        $target->setSeoNoFollow(false);
        $target->setSeoHideInSitemap(true);

        new SeoMerger()->merge($target, $this->row());

        self::assertSame('shared', $target->getSeoTitle());
        self::assertSame('shared', $target->getSeoDescription());
        self::assertSame(['shared'], $target->getSeoKeywords());
        self::assertSame('https://example.org/shared', $target->getSeoCanonicalUrl()?->toString());
        self::assertTrue($target->getSeoNoIndex());
        self::assertFalse($target->getSeoNoFollow());
        self::assertTrue($target->getSeoHideInSitemap());
    }

    public function testTheExcerptOfTheSourceWinsAndKeepsTheSharedImage(): void
    {
        $target = $this->row();
        $target->setExcerptTitle('shared');
        $target->setExcerptDescription('shared');
        $target->setExcerptImage('/images/cover.png');

        $source = $this->row();
        $source->setExcerptTitle('Bonjour');
        $source->setExcerptDescription('Une description');

        new ExcerptMerger()->merge($target, $source);

        self::assertSame('Bonjour', $target->getExcerptTitle());
        self::assertSame('Une description', $target->getExcerptDescription());
        self::assertSame('/images/cover.png', $target->getExcerptImage());

        $other = $this->row();
        $other->setExcerptImage('/images/other.png');
        new ExcerptMerger()->merge($target, $other);

        self::assertSame('/images/other.png', $target->getExcerptImage());
        self::assertSame('Bonjour', $target->getExcerptTitle());
        self::assertSame('Une description', $target->getExcerptDescription());
    }

    public function testTheLinkOfTheSourceReplacesTheTargetsWhole(): void
    {
        $target = $this->row();
        $target->setLink(Link::toContent('article', '42', Locale::fromString('fr')));

        $source = $this->row();
        $source->setLink(Link::toUrl(Url::fromString('https://example.org/')));

        new LinkMerger()->merge($target, $source);
        new LinkMerger()->merge($target, $this->row());

        self::assertSame('https://example.org/', $target->getLink()?->url?->toString());
        self::assertNull($target->getLink()->locale);
    }

    public function testTheSlugOfTheSourceWins(): void
    {
        $target = $this->row();
        $target->setSlug(Slug::fromString('shared'));

        $source = $this->row();
        $source->setSlug(Slug::fromString('bonjour'));

        new RoutableMerger()->merge($target, $source);
        new RoutableMerger()->merge($target, $this->row());

        self::assertSame('bonjour', $target->getSlug()?->value);
    }

    public function testTheBodyOfTheSourceReplacesTheTargetsWhole(): void
    {
        $target = $this->row();
        $target->setBody(Body::of('shared', MarkupFormat::Markdown, '<p>shared</p>'));

        $source = $this->row();
        $source->setBody(Body::of('<p>Bonjour</p>', MarkupFormat::Html, '<p>Bonjour</p>'));

        new BodyMerger()->merge($target, $source);
        new BodyMerger()->merge($target, $this->row());

        self::assertSame('<p>Bonjour</p>', $target->getBody()?->source);
        self::assertSame('html', $target->getBody()->format);
    }

    #[DataProvider('mergers')]
    public function testARowWithoutTheCapabilityIsLeftAlone(Merger $merger): void
    {
        $row = $this->row();
        $row->setSeoTitle('kept');
        $row->setExcerptTitle('kept');
        $row->setLink(Link::toContent('article', '1'));
        $row->setSlug(Slug::fromString('kept'));
        $row->setBody(Body::of('kept', MarkupFormat::Markdown, '<p>kept</p>'));

        $bare = new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));

        $merger->merge($row, $bare);
        $merger->merge($bare, $row);

        self::assertSame('kept', $row->getSeoTitle());
        self::assertSame('kept', $row->getExcerptTitle());
        self::assertSame('1', $row->getLink()?->resourceId);
        self::assertSame('kept', $row->getSlug()?->value);
        self::assertSame('kept', $row->getBody()?->source);
    }

    /**
     * @return iterable<string, array{Merger}>
     */
    public static function mergers(): iterable
    {
        yield 'SEO' => [new SeoMerger()];
        yield 'excerpt' => [new ExcerptMerger()];
        yield 'link' => [new LinkMerger()];
        yield 'routable' => [new RoutableMerger()];
        yield 'body' => [new BodyMerger()];
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first()));
    }
}
