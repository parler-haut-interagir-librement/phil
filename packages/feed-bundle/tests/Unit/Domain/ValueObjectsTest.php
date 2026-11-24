<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Category;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\FeedBundle\Domain\ValueObject\Text;
use Phil\FeedBundle\Tests\Fixtures\Entries;
use Phil\FeedBundle\Tests\Fixtures\SampleFeeds;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Text::class)]
#[CoversClass(InvalidFeed::class)]
#[CoversClass(Author::class)]
#[CoversClass(Category::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(FeedItem::class)]
#[CoversClass(Channel::class)]
#[CoversClass(FeedQuery::class)]
#[CoversClass(FeedPage::class)]
#[CoversClass(FeedLinks::class)]
#[CoversClass(Feed::class)]
#[CoversClass(FeedFormat::class)]
final class ValueObjectsTest extends TestCase
{
    public function testTextsAreTrimmed(): void
    {
        $item = new FeedItem('urn:x:1', '  Title  ', self::url(), self::date(), summary: "  \n ", contentHtml: ' <p>x</p> ', language: 'zh-Hant-TW');

        self::assertSame(['Title', null, '<p>x</p>', 'zh-Hant-TW'], [$item->title, $item->summary, $item->contentHtml, $item->language]);
        self::assertSame('Desk', new Author(' Desk ')->name);
        self::assertSame(['a', 'b'], [new Category(' a ', ' b ')->term, new Category('a', ' b ')->label]);
        self::assertSame('Title', new Channel(' Title ', self::url(), ' ')->title);
        self::assertNull(new Channel('Title', self::url(), ' ')->description);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty title' => [static fn (): FeedItem => new FeedItem('urn:x:1', ' ', self::url(), self::date()), 'The entry title " " is refused: 1 to 1024 characters on one line.'];
        yield 'title on two lines' => [static fn (): FeedItem => new FeedItem('urn:x:1', "a\nb", self::url(), self::date()), 'The entry title "a'."\n".'b" is refused: 1 to 1024 characters on one line.'];
        yield 'long title' => [static fn (): FeedItem => new FeedItem('urn:x:1', str_repeat('é', 1025), self::url(), self::date()), 'The entry title "'.str_repeat('é', 40).'" is refused: 1 to 1024 characters on one line.'];
        yield 'title not UTF-8' => [static fn (): FeedItem => new FeedItem('urn:x:1', "\xC3", self::url(), self::date()), 'The entry title "(not UTF-8)" is refused: not UTF-8.'];
        yield 'id without scheme' => [static fn (): FeedItem => new FeedItem('news-42', 'T', self::url(), self::date()), 'The entry id "news-42" is refused: an absolute IRI of at most 2048 bytes, such as tag:example.org,2026:news-42 or https://example.org/news/42.'];
        yield 'id with a space' => [static fn (): FeedItem => new FeedItem('urn:a b', 'T', self::url(), self::date()), 'an absolute IRI'];
        yield 'id empty after the scheme' => [static fn (): FeedItem => new FeedItem('urn:', 'T', self::url(), self::date()), 'an absolute IRI'];
        yield 'id with a scheme starting by a digit' => [static fn (): FeedItem => new FeedItem('1urn:x', 'T', self::url(), self::date()), 'an absolute IRI'];
        yield 'id not UTF-8' => [static fn (): FeedItem => new FeedItem("urn:\xC3", 'T', self::url(), self::date()), 'not UTF-8'];
        yield 'long id' => [static fn (): FeedItem => new FeedItem('urn:'.str_repeat('a', 2045), 'T', self::url(), self::date()), 'an absolute IRI of at most 2048 bytes'];
        yield 'long summary' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), summary: str_repeat('a', 65_537)), 'The entry summary "'.str_repeat('a', 40).'" is refused: at most 65536 bytes.'];
        yield 'summary not UTF-8' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), summary: "\xFF"), 'The entry summary "(not UTF-8)" is refused: not UTF-8.'];
        yield 'long content' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), contentHtml: str_repeat('a', 1_048_577)), 'at most 1048576 bytes'];
        yield 'language with an underscore' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), language: 'fr_CA'), 'The language "fr_CA" is refused: a BCP 47 tag such as fr or fr-CA.'];
        yield 'long language' => [static fn (): Channel => new Channel('T', self::url(), language: 'fr-'.str_repeat('abcdefgh-', 4)), 'a BCP 47 tag'];
        yield 'too many authors' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), authors: array_fill(0, 33, new Author('A'))), 'A feed holds at most 32 authors in an entry; got 33.'];
        yield 'too many categories' => [static fn (): FeedItem => new FeedItem('urn:x:1', 'T', self::url(), self::date(), categories: array_fill(0, 65, new Category('c'))), 'A feed holds at most 64 categories in an entry; got 65.'];
        yield 'too many authors of a feed' => [static fn (): Channel => new Channel('T', self::url(), authors: array_fill(0, 33, new Author('A'))), 'A feed holds at most 32 authors in a feed; got 33.'];
        yield 'long feed title' => [static fn (): Channel => new Channel(str_repeat('a', 256), self::url()), '1 to 255 characters'];
        yield 'long description' => [static fn (): Channel => new Channel('T', self::url(), str_repeat('a', 4097)), 'at most 4096 bytes'];
        yield 'empty author' => [static fn (): Author => new Author(''), 'The author name "" is refused: 1 to 255 characters on one line.'];
        yield 'long author' => [static fn (): Author => new Author(str_repeat('a', 256)), '1 to 255 characters'];
        yield 'long term' => [static fn (): Category => new Category(str_repeat('a', 256)), 'The category term'];
        yield 'long label' => [static fn (): Category => new Category('a', str_repeat('a', 256)), 'The category label'];
        yield 'media type without subtype' => [static fn (): Attachment => new Attachment(self::url(), 'audio'), 'The attachment media type "audio" is refused: a media type such as audio/mpeg, without parameters.'];
        yield 'media type with parameters' => [static fn (): Attachment => new Attachment(self::url(), 'audio/mpeg; q=1'), 'media type'];
        yield 'negative size' => [static fn (): Attachment => new Attachment(self::url(), 'audio/mpeg', -1), 'The attachment size "-1" is refused: 0 bytes or more.'];
        yield 'negative duration' => [static fn (): Attachment => new Attachment(self::url(), 'audio/mpeg', durationInSeconds: -1), 'The attachment duration "-1" is refused: 0 seconds or more.'];
        yield 'empty attachment title' => [static fn (): Attachment => new Attachment(self::url(), 'audio/mpeg', title: ''), 'The attachment title'];
        yield 'long attachment title' => [static fn (): Attachment => new Attachment(self::url(), 'audio/mpeg', title: str_repeat('a', 256)), '1 to 255 characters'];
        yield 'limit 0' => [static fn (): FeedQuery => new FeedQuery(0), 'The limit "0" is refused: 1 to 500 entries.'];
        yield 'limit 501' => [static fn (): FeedQuery => new FeedQuery(501), 'The limit "501" is refused'];
        yield 'page 0' => [static fn (): FeedQuery => new FeedQuery(1, 0), 'The page "0" is refused: 1 to 1000.'];
        yield 'page 1001' => [static fn (): FeedQuery => new FeedQuery(1, 1001), 'The page "1001" is refused'];
        yield 'too many entries' => [static fn (): Feed => new Feed(new Channel('T', self::url()), array_fill(0, 501, Entries::item())), 'A feed holds at most 500 entries; got 501.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testAPartNoFormatCouldWriteIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidFeed::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheBoundsAreAccepted(): void
    {
        $item = new FeedItem('urn:'.str_repeat('a', 2044), str_repeat('é', 1024), self::url(), self::date(), summary: str_repeat('a', 65_536), contentHtml: str_repeat('a', 1_048_576), authors: array_fill(0, 32, new Author(str_repeat('a', 255))), categories: array_fill(0, 64, new Category(str_repeat('a', 255), str_repeat('b', 255))), language: 'fr-'.str_repeat('a', 8).'-'.str_repeat('b', 8).'-'.str_repeat('c', 8).'-12345');

        self::assertSame(2048, \strlen($item->id));
        self::assertSame(35, \strlen((string) $item->language));
        self::assertSame(str_repeat('a', 255), new Channel(str_repeat('a', 255), self::url(), str_repeat('d', 4096), authors: array_fill(0, 32, new Author('A')))->title);
        self::assertSame([500, 1000, 499_500], [new FeedQuery(500, 1000)->limit, new FeedQuery(500, 1000)->page, new FeedQuery(500, 1000)->offset()]);
        self::assertSame([0, 1], [new FeedQuery(1)->offset(), new FeedQuery(1)->page]);
        self::assertCount(500, new Feed(new Channel('T', self::url()), array_fill(0, 500, Entries::item()))->items);
        self::assertSame(0, new Attachment(self::url(), 'audio/mpeg', 0, durationInSeconds: 0)->sizeInBytes);
        self::assertSame(0, new Attachment(self::url(), 'audio/mpeg', 0, durationInSeconds: 0)->durationInSeconds);
    }

    public function testTheUpdateOfAnEntryIsNeverBeforeItsPublication(): void
    {
        $published = new DateTimeImmutable('2026-11-21T10:00:00Z');

        self::assertSame($published, new FeedItem('urn:x:1', 'T', self::url(), $published)->updated);
        self::assertSame($published, new FeedItem('urn:x:1', 'T', self::url(), $published, new DateTimeImmutable('2026-11-21T09:59:59Z'))->updated);
        $later = new DateTimeImmutable('2026-11-21T10:00:01Z');
        self::assertSame($later, new FeedItem('urn:x:1', 'T', self::url(), $published, $later)->updated);
    }

    public function testAnAttachmentReadsItsMediaTypeInLowercase(): void
    {
        $attachment = new Attachment(self::url(), 'Audio/MPEG', 12, 'Episode', 60);

        self::assertSame(['audio/mpeg', 12, 'Episode', 60], [$attachment->mediaType, $attachment->sizeInBytes, $attachment->title, $attachment->durationInSeconds]);
        self::assertSame('application/vnd.example+json', new Attachment(self::url(), 'application/vnd.example+json')->mediaType);
    }

    public function testACategoryIsShownByItsLabelElseItsTerm(): void
    {
        self::assertSame('Label', new Category('term', 'Label')->display());
        self::assertSame('term', new Category('term')->display());
    }

    public function testAFeedIsUpdatedWhenItsLatestEntryIs(): void
    {
        $feed = SampleFeeds::full();

        self::assertSame('2026-11-21T09:00:00+00:00', $feed->updated->setTimezone(new DateTimeZone('UTC'))->format(\DATE_ATOM));
        self::assertFalse($feed->isEmpty());
        self::assertSame(0, SampleFeeds::empty()->updated->getTimestamp());
        self::assertTrue(SampleFeeds::empty()->isEmpty());
        self::assertSame([1, false], [$feed->page, $feed->hasNextPage]);
    }

    public function testTheFingerprintChangesWithWhatTheFeedShows(): void
    {
        $channel = new Channel('T', self::url());
        $item = Entries::item();
        $base = new Feed($channel, [$item]);

        self::assertSame($base->fingerprint(), new Feed($channel, [$item])->fingerprint());
        self::assertSame(32, \strlen($base->fingerprint()));
        // Pinned, so that the same feed keeps its ETag from one version of the bundle to the next.
        self::assertSame('2809f5c39053b936e473038bd1cbcd51', $base->fingerprint());
        self::assertSame('7f192025719ad0546a5425f44fd964fb', new Feed($channel, [$item], 2, true)->fingerprint());

        $others = [
            new Feed(new Channel('U', self::url()), [$item]),
            new Feed(new Channel('T', Url::fromString('https://example.org/other')), [$item]),
            new Feed(new Channel('T', self::url(), 'D'), [$item]),
            new Feed(new Channel('T', self::url(), language: 'fr'), [$item]),
            new Feed(new Channel('T', self::url(), icon: self::url()), [$item]),
            new Feed(new Channel('T', self::url(), authors: [new Author('A')]), [$item]),
            new Feed($channel, [$item], 2),
            new Feed($channel, [$item], 1, true),
            new Feed($channel, [Entries::item(id: 'urn:other:1')]),
            new Feed($channel, [new FeedItem($item->id, 'T', self::url(), $item->published, $item->published->modify('+1 microsecond'))]),
            new Feed($channel, []),
            new Feed($channel, [$item, $item]),
        ];

        $fingerprints = array_map(static fn (Feed $feed): string => $feed->fingerprint(), $others);
        self::assertNotContains($base->fingerprint(), $fingerprints);
        self::assertCount(\count($others), array_unique($fingerprints));
    }

    public function testFeedLinksAreNamedByTheirFirstPage(): void
    {
        $self = Url::fromString('https://example.org/feeds/news.atom?page=2');
        $first = Url::fromString('https://example.org/feeds/news.atom');

        self::assertSame($first, new FeedLinks($self, first: $first)->feedId());
        self::assertSame($self, new FeedLinks($self)->feedId());
        self::assertTrue([] === new FeedPage([])->items && !new FeedPage([])->hasNextPage);
    }

    public function testTheFormats(): void
    {
        self::assertSame(['application/atom+xml', 'application/rss+xml', 'application/feed+json'], array_map(static fn (FeedFormat $format): string => $format->mediaType(), FeedFormat::cases()));
        self::assertSame(['application/atom+xml; charset=UTF-8', 'application/rss+xml; charset=UTF-8', 'application/feed+json; charset=UTF-8'], array_map(static fn (FeedFormat $format): string => $format->contentType(), FeedFormat::cases()));
        self::assertSame(['Atom', 'RSS', 'JSON Feed'], array_map(static fn (FeedFormat $format): string => $format->label(), FeedFormat::cases()));
    }

    private static function url(): Url
    {
        return Url::fromString('https://example.org/entry');
    }

    private static function date(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-21T08:30:00Z');
    }
}
