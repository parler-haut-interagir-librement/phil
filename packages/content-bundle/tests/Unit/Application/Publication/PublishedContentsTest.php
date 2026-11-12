<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublishedContent::class)]
#[CoversClass(PublishedContents::class)]
#[CoversClass(PublishedRowQuery::class)]
final class PublishedContentsTest extends TestCase
{
    private InMemoryContents $contents;

    private ContentManager $manager;

    private PublishedContents $published;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
        $this->manager = $this->contents->manager;
        $this->published = $this->contents->published;
    }

    public function testALocaleListsWhatItPublishesItself(): void
    {
        $first = $this->manager->create('example', 'fr', ['excerptTitle' => 'Premier', 'excerptDescription' => 'Le premier', 'seoTitle' => 'Titre SEO', 'seoDescription' => 'Description SEO']);
        $this->manager->publish($first, 'fr');
        $second = $this->manager->create('example', 'fr', ['seoTitle' => 'Second', 'seoDescription' => 'Le second', 'seoHideInSitemap' => true]);
        $this->manager->publish($second, 'fr');
        $this->manager->create('example', 'fr', ['excerptTitle' => 'Brouillon']);
        $this->manager->edit($first, 'en', ['excerptTitle' => 'First']);

        $french = $this->inLocale('example', Locale::fromString('fr'));
        self::assertSame([['example:1', 'fr', 'Premier', 'Le premier', false], ['example:2', 'fr', 'Second', 'Le second', true]], array_map(static fn (PublishedContent $content): array => [$content->reference->toString(), $content->locale->value, $content->title(), $content->summary(), $content->isHiddenFromSitemaps()], $french));
        self::assertSame('2026-10-31T16:00:00+00:00', $french[0]->publishedAt()?->format('c'));
        self::assertSame([], $this->inLocale('example', Locale::fromString('en')));
        self::assertSame([], $this->inLocale('other', Locale::fromString('fr')));

        $hidden = $this->manager->create('example', 'en', ['seoNoIndex' => true]);
        $this->manager->publish($hidden, 'en');
        $listed = $this->manager->create('example', 'en', ['seoNoIndex' => false, 'seoHideInSitemap' => false]);
        $this->manager->publish($listed, 'en');
        self::assertSame([true, false], array_map(static fn (PublishedContent $content): bool => $content->isHiddenFromSitemaps(), $this->inLocale('example', Locale::fromString('en'))));
    }

    /**
     * Five contents in chunks of two: two full chunks, then the last one; each chunk starts where the one before ended.
     */
    public function testTheChunksFollowEachOther(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $title) {
            $this->manager->publish($this->manager->create('example', 'fr', ['excerptTitle' => $title]), 'fr');
        }

        $chunks = [];

        foreach ($this->published->chunks('example', Locale::fromString('fr'), 2) as $chunk) {
            $chunks[] = $this->titles($chunk);

            if (\count($chunks) > 3) {
                break;
            }
        }

        self::assertSame([['a', 'b'], ['c', 'd'], ['e']], $chunks);
    }

    public function testTheLatestComeFirst(): void
    {
        $references = [];

        foreach (['2026-11-01', '2026-11-03', '2026-11-02'] as $day) {
            $this->contents->clock->modify($day.' 12:00');
            $reference = $this->manager->create('example', 'fr', ['excerptTitle' => $day]);
            $this->manager->publish($reference, 'fr');
            $references[$day] = $reference->toString();
        }

        self::assertSame(['2026-11-03', '2026-11-02', '2026-11-01'], $this->titles($this->published->latest('example', Locale::fromString('fr'), 10)));
        self::assertSame(['2026-11-03', '2026-11-02'], $this->titles($this->published->latest('example', Locale::fromString('fr'), 2)));
    }

    public function testAPageOfContentsIsFoundByIdentifierAndBySlug(): void
    {
        foreach (['c' => 'charlie', 'a' => 'alpha', 'b' => null] as $title => $slug) {
            $reference = $this->manager->create('example', 'fr', ['excerptTitle' => $title, 'slug' => $slug]);
            $this->manager->publish($reference, 'fr');
        }

        $french = Locale::fromString('fr');

        self::assertSame(['a', 'c'], $this->titles($this->published->find(new PublishedRowQuery('example', $french, PublishedOrder::Slug, withSlug: true))));
        self::assertSame(['a', 'b'], $this->titles($this->published->find(new PublishedRowQuery('example', $french, resourceIds: ['3', '2']))), 'In the order of the roots, not of the identifiers given.');
        self::assertSame(['a'], $this->titles($this->published->find(new PublishedRowQuery('example', $french, offset: 1, limit: 1))));
        self::assertSame(['c', 'a'], $this->titles($this->published->latest('example', $french, 5, true)), 'Published at once, by root; the one without a slug left out.');
    }

    public function testAQueryOfPublishedRowsRefusesANegativeOffset(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The offset of a query of published rows is 0 or more; got -1.');

        new PublishedRowQuery('example', Locale::fromString('fr'), offset: -1);
    }

    public function testAQueryOfPublishedRowsRefusesALimitBelowOne(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The limit of a query of published rows is 1 or more; got 0.');

        new PublishedRowQuery('example', Locale::fromString('fr'), limit: 0);
    }

    public function testADraftIsReadForItsPreview(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Brouillon']);
        $root = $this->contents->roots->find($reference);
        self::assertNotNull($root);

        self::assertSame('Brouillon', new PublishedContent($reference, Locale::fromString('fr'), $this->published->draft($root, Locale::fromString('fr')) ?? self::fail())->title());
        self::assertNull($this->published->draft($root, Locale::fromString('en')));
    }

    public function testARowWithoutCapabilitiesHasItsReferenceForTitle(): void
    {
        $reference = ContentReference::of('example', '9');
        $bare = new PublishedContent($reference, Locale::fromString('fr'), new BareDimensionContent(new ExampleRoot(), DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first())));

        self::assertSame(['example:9', null, null, false], [$bare->title(), $bare->summary(), $bare->publishedAt(), $bare->isHiddenFromSitemaps()]);
    }

    /**
     * Every content the locale publishes, read in chunks of two to cross a chunk's end.
     *
     * @return list<PublishedContent>
     */
    private function inLocale(string $resourceKey, Locale $locale): array
    {
        $contents = [];

        foreach ($this->published->chunks($resourceKey, $locale, 2) as $chunk) {
            self::assertNotSame([], $chunk);
            $contents = [...$contents, ...$chunk];
        }

        return $contents;
    }

    /**
     * @param list<PublishedContent> $contents
     *
     * @return list<string>
     */
    private function titles(array $contents): array
    {
        return array_map(static fn (PublishedContent $content): string => $content->title(), $contents);
    }
}
