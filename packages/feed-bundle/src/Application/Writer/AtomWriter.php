<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Writer;

use Override;
use Phil\FeedBundle\Application\Contract\FeedWriter;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\Url\Url;
use XMLWriter;

/**
 * Atom, RFC 4287, written by `XMLWriter`.
 *
 * - The feed is named by its first page (`FeedLinks::feedId()`), and links to itself, its page of the site, its hubs
 *   and, in a paged feed, its other pages; it is `updated` when its latest entry is.
 * - Atom asks for an author of the feed unless every entry has one: when the channel names none, and the feed has no
 *   entry or an entry has none, the feed's title is written as its author.
 * - An entry writes its title as text, its summary as text, its content as escaped HTML (`type="html"`), its
 *   categories with their term and label, and its attachment as a `rel="enclosure"` link.
 * - Dates are RFC 3339 in UTC.
 */
final readonly class AtomWriter implements FeedWriter
{
    public const string ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    #[Override]
    public function format(): FeedFormat
    {
        return FeedFormat::Atom;
    }

    #[Override]
    public function write(Feed $feed, FeedLinks $links): string
    {
        $channel = $feed->channel;
        $xml = XmlText::open();
        $xml->startElement('feed');
        $xml->writeAttribute('xmlns', self::ATOM_NAMESPACE);
        XmlText::attributes($xml, ['xml:lang' => $channel->language]);

        XmlText::element($xml, 'id', $links->feedId()->toString());
        XmlText::element($xml, 'title', $channel->title);

        if (null !== $channel->description) {
            XmlText::element($xml, 'subtitle', $channel->description);
        }

        XmlText::element($xml, 'updated', XmlText::rfc3339($feed->updated));
        XmlText::empty($xml, 'link', ['rel' => 'self', 'type' => FeedFormat::Atom->mediaType(), 'href' => $links->self->toString()]);
        XmlText::empty($xml, 'link', ['rel' => 'alternate', 'type' => 'text/html', 'href' => $channel->homePage->toString()]);

        foreach ($links->hubs as $hub) {
            XmlText::empty($xml, 'link', ['rel' => 'hub', 'href' => $hub->toString()]);
        }

        foreach (['first' => $links->first, 'previous' => $links->previous, 'next' => $links->next] as $rel => $url) {
            if ($url instanceof Url) {
                XmlText::empty($xml, 'link', ['rel' => $rel, 'type' => FeedFormat::Atom->mediaType(), 'href' => $url->toString()]);
            }
        }

        if ($channel->icon instanceof Url) {
            XmlText::element($xml, 'icon', $channel->icon->toString());
        }

        $authors = $channel->authors;

        if ([] === $authors && $this->anEntryHasNoAuthor($feed)) {
            $authors = [new Author($channel->title)];
        }

        foreach ($authors as $author) {
            $this->author($xml, $author);
        }

        foreach ($feed->items as $item) {
            $this->entry($xml, $item);
        }

        // XmlText::close() ends the document, which closes the feed element.
        return XmlText::close($xml);
    }

    /**
     * Whether the feed needs an author of its own: it has no entry, or an entry has no author.
     */
    private function anEntryHasNoAuthor(Feed $feed): bool
    {
        if ($feed->isEmpty()) {
            return true;
        }

        return array_any($feed->items, static fn (FeedItem $item): bool => [] === $item->authors);
    }

    private function entry(XMLWriter $xml, FeedItem $item): void
    {
        $xml->startElement('entry');
        XmlText::attributes($xml, ['xml:lang' => $item->language]);
        XmlText::element($xml, 'id', $item->id);
        $xml->startElement('title');
        $xml->writeAttribute('type', 'text');
        $xml->text(XmlText::clean($item->title));
        $xml->endElement();
        XmlText::empty($xml, 'link', ['rel' => 'alternate', 'type' => 'text/html', 'href' => $item->url->toString()]);
        XmlText::element($xml, 'published', XmlText::rfc3339($item->published));
        XmlText::element($xml, 'updated', XmlText::rfc3339($item->updated));

        foreach ($item->authors as $author) {
            $this->author($xml, $author);
        }

        foreach ($item->categories as $category) {
            XmlText::empty($xml, 'category', ['term' => $category->term, 'label' => $category->label]);
        }

        if (null !== $item->summary) {
            $xml->startElement('summary');
            $xml->writeAttribute('type', 'text');
            $xml->text(XmlText::clean($item->summary));
            $xml->endElement();
        }

        if (null !== $item->contentHtml) {
            $xml->startElement('content');
            $xml->writeAttribute('type', 'html');
            $xml->text(XmlText::clean($item->contentHtml));
            $xml->endElement();
        }

        $attachment = $item->attachment;

        if ($attachment instanceof Attachment) {
            XmlText::empty($xml, 'link', [
                'rel' => 'enclosure',
                'type' => $attachment->mediaType,
                'href' => $attachment->url->toString(),
                'length' => null === $attachment->sizeInBytes ? null : (string) $attachment->sizeInBytes,
                'title' => $attachment->title,
            ]);
        }

        $xml->endElement();
    }

    private function author(XMLWriter $xml, Author $author): void
    {
        $xml->startElement('author');
        XmlText::element($xml, 'name', $author->name);

        if ($author->url instanceof Url) {
            XmlText::element($xml, 'uri', $author->url->toString());
        }

        $xml->endElement();
    }
}
