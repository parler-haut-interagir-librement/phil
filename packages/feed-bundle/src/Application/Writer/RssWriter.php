<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Writer;

use Override;
use Phil\FeedBundle\Application\Contract\FeedWriter;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\Url\Url;
use XMLWriter;

/**
 * RSS 2.0, for the readers that read nothing else, written by `XMLWriter`.
 *
 * - The channel has its title, its page of the site and a description — the channel's, else its title, since RSS asks
 *   for one —, its language, `lastBuildDate` when it has entries, and its own address, its hubs and its other pages as
 *   `atom:link`, as the RSS Advisory Board recommends.
 * - An item writes `guid` with `isPermaLink="true"` only when its identifier is its address; its description is its
 *   summary, escaped into HTML, else its content; its whole content goes in `content:encoded`; its authors are
 *   `dc:creator`, since RSS's own `author` is an e-mail address; its attachment is its `enclosure`, with a length of 0
 *   when the size is unknown.
 * - Dates are RFC 822 in UTC.
 */
final readonly class RssWriter implements FeedWriter
{
    public const string CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';

    public const string DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    #[Override]
    public function format(): FeedFormat
    {
        return FeedFormat::Rss;
    }

    #[Override]
    public function write(Feed $feed, FeedLinks $links): string
    {
        $channel = $feed->channel;
        $xml = XmlText::open();
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', AtomWriter::ATOM_NAMESPACE);
        $xml->writeAttribute('xmlns:content', self::CONTENT_NAMESPACE);
        $xml->writeAttribute('xmlns:dc', self::DC_NAMESPACE);
        $xml->startElement('channel');

        XmlText::element($xml, 'title', $channel->title);
        XmlText::element($xml, 'link', $channel->homePage->toString());
        XmlText::element($xml, 'description', $channel->description ?? $channel->title);

        if (null !== $channel->language) {
            XmlText::element($xml, 'language', $channel->language);
        }

        if (!$feed->isEmpty()) {
            XmlText::element($xml, 'lastBuildDate', XmlText::rfc822($feed->updated));
        }

        XmlText::empty($xml, 'atom:link', ['rel' => 'self', 'type' => FeedFormat::Rss->mediaType(), 'href' => $links->self->toString()]);

        foreach ($links->hubs as $hub) {
            XmlText::empty($xml, 'atom:link', ['rel' => 'hub', 'href' => $hub->toString()]);
        }

        foreach (['first' => $links->first, 'previous' => $links->previous, 'next' => $links->next] as $rel => $url) {
            if ($url instanceof Url) {
                XmlText::empty($xml, 'atom:link', ['rel' => $rel, 'type' => FeedFormat::Rss->mediaType(), 'href' => $url->toString()]);
            }
        }

        if ($channel->icon instanceof Url) {
            $xml->startElement('image');
            XmlText::element($xml, 'url', $channel->icon->toString());
            XmlText::element($xml, 'title', $channel->title);
            XmlText::element($xml, 'link', $channel->homePage->toString());
            $xml->endElement();
        }

        foreach ($feed->items as $item) {
            $this->item($xml, $item);
        }

        // XmlText::close() ends the document, which closes the channel and rss elements.
        return XmlText::close($xml);
    }

    private function item(XMLWriter $xml, FeedItem $item): void
    {
        $url = $item->url->toString();
        $xml->startElement('item');
        XmlText::element($xml, 'title', $item->title);
        XmlText::element($xml, 'link', $url);
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', $item->id === $url ? 'true' : 'false');
        $xml->text(XmlText::clean($item->id));
        $xml->endElement();
        XmlText::element($xml, 'pubDate', XmlText::rfc822($item->published));

        foreach ($item->authors as $author) {
            XmlText::element($xml, 'dc:creator', $author->name);
        }

        foreach ($item->categories as $category) {
            XmlText::element($xml, 'category', $category->display());
        }

        $description = null === $item->summary ? $item->contentHtml : htmlspecialchars($item->summary, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');

        if (null !== $description) {
            XmlText::element($xml, 'description', $description);
        }

        if (null !== $item->contentHtml) {
            XmlText::element($xml, 'content:encoded', $item->contentHtml);
        }

        $attachment = $item->attachment;

        if ($attachment instanceof Attachment) {
            XmlText::empty($xml, 'enclosure', [
                'url' => $attachment->url->toString(),
                'length' => (string) ($attachment->sizeInBytes ?? 0),
                'type' => $attachment->mediaType,
            ]);
        }

        $xml->endElement();
    }
}
