<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Writer;

use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\RssWriter;
use Phil\FeedBundle\Application\Writer\XmlText;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Tests\Fixtures\Entries;
use Phil\FeedBundle\Tests\Fixtures\SampleFeeds;
use Phil\FeedBundle\Tests\Fixtures\Xml;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RssWriter::class)]
#[CoversClass(XmlText::class)]
final class RssWriterTest extends TestCase
{
    private const array NAMESPACES = ['atom' => AtomWriter::ATOM_NAMESPACE, 'content' => RssWriter::CONTENT_NAMESPACE, 'dc' => RssWriter::DC_NAMESPACE];

    public function testAFullFeedIsWrittenAsExpected(): void
    {
        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/full.rss', new RssWriter()->write(SampleFeeds::full(), SampleFeeds::links()));
        self::assertSame(FeedFormat::Rss, new RssWriter()->format());
    }

    /**
     * RSS 2.0: a channel has a title, a link and a description; each item a title or a description.
     */
    public function testTheElementsRssRequiresAreThere(): void
    {
        $xpath = Xml::read(new RssWriter()->write(SampleFeeds::full(), SampleFeeds::links()), self::NAMESPACES);

        self::assertSame('2.0', Xml::text($xpath, '/rss/@version'));

        foreach (['title', 'link', 'description'] as $element) {
            self::assertSame(1, Xml::count($xpath, '/rss/channel/'.$element), $element);
        }

        self::assertSame(2, Xml::count($xpath, '/rss/channel/item/title'));
        self::assertSame('Un résumé où 2 &lt; 3 &amp; « guillemets ».', Xml::text($xpath, '/rss/channel/item[1]/description'));
        self::assertSame('<p>Le <em>contenu</em> &amp; une <a href="https://example.org/?a=1&amp;b=2">adresse</a>.</p>', Xml::text($xpath, '/rss/channel/item[1]/content:encoded'));
        self::assertSame('Zoé Lévesque', Xml::text($xpath, '/rss/channel/item[1]/dc:creator'));
        self::assertSame('https://hub.example.net/', Xml::text($xpath, '/rss/channel/atom:link[@rel="hub"]/@href'));
    }

    public function testTheQuotesOfASummaryAreEscapedAsHtml(): void
    {
        $xpath = Xml::read(new RssWriter()->write(Entries::feed(Entries::item(summary: 'Tom\'s "best"')), SampleFeeds::plainLinks()), self::NAMESPACES);

        self::assertSame('Tom&apos;s &quot;best&quot;', Xml::text($xpath, '/rss/channel/item/description'));
    }

    public function testAnEmptyFeedDescribesItselfByItsTitle(): void
    {
        $rss = new RssWriter()->write(SampleFeeds::empty(), SampleFeeds::plainLinks());

        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/empty.rss', $rss);
        $xpath = Xml::read($rss, self::NAMESPACES);
        self::assertSame('Empty', Xml::text($xpath, '/rss/channel/description'));
        self::assertSame(0, Xml::count($xpath, '//lastBuildDate | //item | //language | //image'));
    }

    public function testTheDescriptionIsTheContentWhenThereIsNoSummary(): void
    {
        $xpath = Xml::read(new RssWriter()->write(Entries::feed(Entries::item(content: '<p>Body</p>'), Entries::item('Bare')), SampleFeeds::plainLinks()), self::NAMESPACES);

        self::assertSame('<p>Body</p>', Xml::text($xpath, '/rss/channel/item[1]/description'));
        self::assertSame(0, Xml::count($xpath, '/rss/channel/item[2]/description | /rss/channel/item[2]/content:encoded'));
        self::assertSame('false', Xml::text($xpath, '/rss/channel/item[1]/guid/@isPermaLink'));
    }

    public function testAnAttachmentOfUnknownSizeHasALengthOfZero(): void
    {
        $item = Entries::item(attachment: new Attachment(Url::fromString('https://example.org/a.ogg'), 'audio/ogg'));
        $xpath = Xml::read(new RssWriter()->write(Entries::feed($item), SampleFeeds::plainLinks()), self::NAMESPACES);

        self::assertSame(1, Xml::count($xpath, '//enclosure[@url="https://example.org/a.ogg" and @length="0" and @type="audio/ogg"]'));
    }

    public function testCharactersXmlCannotHoldAreDropped(): void
    {
        $xpath = Xml::read(new RssWriter()->write(Entries::feed(Entries::item(content: "<p>x\u{0}y\u{B}z</p>")), SampleFeeds::plainLinks()), self::NAMESPACES);

        self::assertSame('<p>xyz</p>', Xml::text($xpath, '//content:encoded'));
    }
}
