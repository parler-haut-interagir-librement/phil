<?php

declare(strict_types=1);

/*
 * Entries nobody wrote, written in every format: the input is cut at each byte 0 into a title, a summary, an HTML
 * content, a category and an author. Whatever they hold, the entry is refused with InvalidFeed, or each writer gives a
 * document its parser reads back: well-formed XML for Atom and RSS, JSON for JSON Feed, with the title it was given,
 * less the characters XML cannot hold.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\JsonFeedWriter;
use Phil\FeedBundle\Application\Writer\RssWriter;
use Phil\FeedBundle\Application\Writer\XmlText;
use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Category;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$links = new FeedLinks(Url::fromString('https://example.org/feeds/fuzz.atom'));
$url = Url::fromString('https://example.org/entry');
$date = new DateTimeImmutable('2026-11-21T08:30:00Z');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($links, $url, $date): void {
    [$title, $summary, $content, $category, $author] = array_pad(explode("\0", $input, 5), 5, '');

    try {
        $item = new FeedItem('urn:fuzz:1', '' === $title ? 'T' : $title, $url, $date, null, $summary, $content, '' === $author ? [] : [new Author($author)], '' === $category ? [] : [new Category($category)]);
        $feed = new Feed(new Channel('' === $title ? 'T' : $title, $url, $summary), [$item]);
    } catch (InvalidFeed) {
        return;
    }

    foreach ([new AtomWriter(), new RssWriter()] as $writer) {
        $document = new DOMDocument();

        if (!$document->loadXML($writer->write($feed, $links), \LIBXML_NONET)) {
            throw new LogicException($writer::class.' wrote XML its parser cannot read.');
        }

        $titles = $document->getElementsByTagName('title');

        if (XmlText::clean($item->title) !== $titles->item(0)?->textContent) {
            throw new LogicException($writer::class.' changed the title.');
        }
    }

    $json = json_decode(new JsonFeedWriter()->write($feed, $links), true, 32, \JSON_THROW_ON_ERROR);

    if (!is_array($json) || !is_array($json['items'] ?? null) || !is_array($json['items'][0] ?? null) || $item->title !== ($json['items'][0]['title'] ?? null)) {
        throw new LogicException('The JSON Feed lost the title.');
    }
});
