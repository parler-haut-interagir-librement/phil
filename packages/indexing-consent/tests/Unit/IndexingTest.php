<?php

declare(strict_types=1);

namespace Phil\IndexingConsent\Tests\Unit;

use Phil\IndexingConsent\Contract\HasIndexingConsent;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\IndexingConsent\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Indexing::class)]
#[CoversClass(IndexingPolicy::class)]
#[CoversClass(Reader::class)]
final class IndexingTest extends TestCase
{
    /**
     * @return iterable<string, array{Indexing, Reader, bool}>
     */
    public static function answers(): iterable
    {
        foreach (Reader::cases() as $reader) {
            yield 'listed, '.$reader->value => [Indexing::Listed, $reader, true];
        }

        yield 'not indexed, search engines' => [Indexing::NotIndexed, Reader::SearchEngines, false];
        yield 'not indexed, sitemap' => [Indexing::NotIndexed, Reader::Sitemap, false];
        yield 'not indexed, structured data' => [Indexing::NotIndexed, Reader::StructuredData, false];
        yield 'not indexed, site listings' => [Indexing::NotIndexed, Reader::SiteListings, true];

        foreach (Reader::cases() as $reader) {
            yield 'unlisted, '.$reader->value => [Indexing::Unlisted, $reader, false];
        }
    }

    #[DataProvider('answers')]
    public function testEachReaderIsAnswered(Indexing $indexing, Reader $reader, bool $allowed): void
    {
        self::assertSame($allowed, $indexing->allows($reader));
    }

    public function testTheRobotsAreToldOnlyWhenTheRecordIsWithdrawn(): void
    {
        self::assertSame([], Indexing::Listed->robotsDirectives());
        self::assertNull(Indexing::Listed->robots());
        self::assertSame(['noindex', 'noarchive'], Indexing::NotIndexed->robotsDirectives());
        self::assertSame('noindex, noarchive', Indexing::NotIndexed->robots());
        self::assertSame('noindex, noarchive', Indexing::Unlisted->robots());
    }

    public function testTheStrictestWins(): void
    {
        self::assertSame(Indexing::Listed, Indexing::strictest(Indexing::Listed));
        self::assertSame(Indexing::Listed, Indexing::strictest(Indexing::Listed, Indexing::Listed));
        self::assertSame(Indexing::NotIndexed, Indexing::strictest(Indexing::Listed, Indexing::NotIndexed, Indexing::Listed));
        self::assertSame(Indexing::NotIndexed, Indexing::strictest(Indexing::NotIndexed, Indexing::Listed));
        self::assertSame(Indexing::Unlisted, Indexing::strictest(Indexing::Unlisted, Indexing::NotIndexed));
        self::assertSame(Indexing::Unlisted, Indexing::strictest(Indexing::Listed, Indexing::Unlisted, Indexing::NotIndexed));

        foreach (Indexing::cases() as $value) {
            self::assertFalse($value->isStricterThan($value));
            self::assertFalse(Indexing::Listed->isStricterThan($value));
            self::assertFalse($value->isStricterThan(Indexing::Unlisted));
        }

        self::assertTrue(Indexing::NotIndexed->isStricterThan(Indexing::Listed));
        self::assertTrue(Indexing::Unlisted->isStricterThan(Indexing::Listed));
        self::assertTrue(Indexing::Unlisted->isStricterThan(Indexing::NotIndexed));
    }

    public function testTheValuesAreStable(): void
    {
        self::assertSame(['listed', 'not_indexed', 'unlisted'], array_map(static fn (Indexing $indexing): string => $indexing->value, Indexing::cases()));
        self::assertSame(['search_engines', 'sitemap', 'structured_data', 'site_listings'], array_map(static fn (Reader $reader): string => $reader->value, Reader::cases()));
    }

    public function testARecordAnswersForItselfAndAnyOtherIsListed(): void
    {
        $withdrawn = $this->record(Indexing::NotIndexed);

        self::assertSame(Indexing::NotIndexed, IndexingPolicy::of($withdrawn));
        self::assertSame(Indexing::Listed, IndexingPolicy::of(new stdClass()));
        self::assertFalse(IndexingPolicy::allows($withdrawn, Reader::Sitemap));
        self::assertTrue(IndexingPolicy::allows($withdrawn, Reader::SiteListings));
        self::assertTrue(IndexingPolicy::allows(new stdClass(), Reader::Sitemap));
    }

    public function testAReaderKeepsWhatItMayInOrderWithItsKeys(): void
    {
        $listed = $this->record(Indexing::Listed);
        $hidden = $this->record(Indexing::NotIndexed);
        $unlisted = $this->record(Indexing::Unlisted);
        $plain = new stdClass();
        $records = ['a' => $listed, 'b' => $hidden, 'c' => $unlisted, 'd' => $plain];

        self::assertSame(['a' => $listed, 'd' => $plain], iterator_to_array(IndexingPolicy::filter($records, Reader::Sitemap)));
        self::assertSame(['a' => $listed, 'b' => $hidden, 'd' => $plain], iterator_to_array(IndexingPolicy::filter($records, Reader::SiteListings)));
        self::assertSame([], iterator_to_array(IndexingPolicy::filter([], Reader::Sitemap)));
    }

    private function record(Indexing $indexing): HasIndexingConsent
    {
        return new readonly class($indexing) implements HasIndexingConsent {
            public function __construct(private Indexing $value)
            {
            }

            public function indexing(): Indexing
            {
                return $this->value;
            }
        };
    }

    public function testAQueryKeepsTheValuesAReaderAllows(): void
    {
        self::assertSame([Indexing::Listed], Indexing::allowing(Reader::SearchEngines));
        self::assertSame([Indexing::Listed], Indexing::allowing(Reader::Sitemap));
        self::assertSame([Indexing::Listed], Indexing::allowing(Reader::StructuredData));
        self::assertSame([Indexing::Listed, Indexing::NotIndexed], Indexing::allowing(Reader::SiteListings));
    }

    public function testAPageIsAsNarrowAsTheStrictestOfItsRecords(): void
    {
        $record = new class implements HasIndexingConsent {
            public Indexing $choice = Indexing::NotIndexed;

            public function indexing(): Indexing
            {
                return $this->choice;
            }
        };

        self::assertSame(Indexing::Listed, IndexingPolicy::ofAll());
        self::assertSame(Indexing::Listed, IndexingPolicy::ofAll(new stdClass()));
        self::assertSame(Indexing::NotIndexed, IndexingPolicy::ofAll(new stdClass(), $record, new stdClass()));
        $record->choice = Indexing::Unlisted;
        self::assertSame(Indexing::Unlisted, IndexingPolicy::ofAll(...['a' => $record, 'b' => new stdClass()]));
        self::assertSame('_indexing', IndexingPolicy::REQUEST_ATTRIBUTE);
    }
}
