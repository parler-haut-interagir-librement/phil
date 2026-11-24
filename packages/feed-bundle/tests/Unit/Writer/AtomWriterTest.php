<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Writer;

use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\XmlText;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Tests\Fixtures\Entries;
use Phil\FeedBundle\Tests\Fixtures\SampleFeeds;
use Phil\FeedBundle\Tests\Fixtures\Xml;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtomWriter::class)]
#[CoversClass(XmlText::class)]
final class AtomWriterTest extends TestCase
{
    public function testAFullFeedIsWrittenAsExpected(): void
    {
        $atom = new AtomWriter()->write(SampleFeeds::full(), SampleFeeds::links());

        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/full.atom', $atom);
        self::assertSame(FeedFormat::Atom, new AtomWriter()->format());
    }

    /**
     * RFC 4287: a feed has an id, a title and an updated date; each entry an id, a title, an updated date and, without
     * content, an alternate link.
     */
    public function testTheElementsAtomRequiresAreThere(): void
    {
        $xpath = Xml::read(new AtomWriter()->write(SampleFeeds::full(), SampleFeeds::links()), ['a' => AtomWriter::ATOM_NAMESPACE]);

        foreach (['id', 'title', 'updated'] as $element) {
            self::assertSame(1, Xml::count($xpath, '/a:feed/a:'.$element), $element);
            self::assertSame(2, Xml::count($xpath, '/a:feed/a:entry/a:'.$element), $element);
        }

        self::assertSame(2, Xml::count($xpath, '/a:feed/a:entry/a:link[@rel="alternate"]'));
        self::assertSame('Nouvelles & « échos »', Xml::text($xpath, '/a:feed/a:title'));
        self::assertSame('<p>Le <em>contenu</em> &amp; une <a href="https://example.org/?a=1&amp;b=2">adresse</a>.</p>', Xml::text($xpath, '/a:feed/a:entry[1]/a:content[@type="html"]'));
        self::assertSame('Ouverture <b>officielle</b> & café', Xml::text($xpath, '/a:feed/a:entry[1]/a:title[@type="text"]'));
    }

    public function testAnEmptyFeedNamesItsTitleAsItsAuthor(): void
    {
        $atom = new AtomWriter()->write(SampleFeeds::empty(), SampleFeeds::plainLinks());

        self::assertStringEqualsFile(__DIR__.'/../../Fixtures/empty.atom', $atom);
        $xpath = Xml::read($atom, ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame(0, Xml::count($xpath, '//a:entry'));
        self::assertSame('Empty', Xml::text($xpath, '/a:feed/a:author/a:name'));
    }

    public function testTheFeedHasAnAuthorOfItsOwnOnlyWhenAnEntryHasNone(): void
    {
        $writer = new AtomWriter();
        $byZoe = Entries::item(authors: [new Author('Zoé')]);
        $anonymous = Entries::item('Anonymous');
        $links = SampleFeeds::plainLinks();

        $all = Xml::read($writer->write(Entries::feed($byZoe, $byZoe), $links), ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame(0, Xml::count($all, '/a:feed/a:author'));

        $some = Xml::read($writer->write(Entries::feed($byZoe, $anonymous), $links), ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame('Feed', Xml::text($some, '/a:feed/a:author/a:name'));

        $first = Xml::read($writer->write(Entries::feed($anonymous, $byZoe), $links), ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame('Feed', Xml::text($first, '/a:feed/a:author/a:name'));

        $channel = Xml::read($writer->write(Entries::feedBy([new Author('Desk')], $anonymous), $links), ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame('Desk', Xml::text($channel, '/a:feed/a:author/a:name'));
        self::assertSame(0, Xml::count($channel, '/a:feed/a:author/a:uri'));
    }

    public function testCharactersXmlCannotHoldAreDropped(): void
    {
        $atom = new AtomWriter()->write(Entries::feed(Entries::item(summary: "a\u{1}b\u{FFFE}c\td", content: "<p>x\u{0}y\u{1F}z</p>")), SampleFeeds::plainLinks());

        $xpath = Xml::read($atom, ['a' => AtomWriter::ATOM_NAMESPACE]);
        self::assertSame("abc\td", Xml::text($xpath, '//a:summary'));
        self::assertSame('<p>xyz</p>', Xml::text($xpath, '//a:content'));
    }

    public function testAnAttachmentOfUnknownSizeHasNoLength(): void
    {
        $item = Entries::item(attachment: new Attachment(Url::fromString('https://example.org/a.ogg'), 'audio/ogg'));
        $xpath = Xml::read(new AtomWriter()->write(Entries::feed($item), SampleFeeds::plainLinks()), ['a' => AtomWriter::ATOM_NAMESPACE]);

        self::assertSame(1, Xml::count($xpath, '//a:link[@rel="enclosure" and @type="audio/ogg" and @href="https://example.org/a.ogg" and not(@length) and not(@title)]'));
    }
}
