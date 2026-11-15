<?php

declare(strict_types=1);

/*
 * An item made of text nobody wrote — its identifier, title, description, brand, colour, shipping service and custom
 * label cut from the input at each `|` —: it is refused, or written as one RSS item that parses and gives back its
 * title and its shipping service, and as one TSV row of one cell per field; and nothing else throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\Serializer\RssSerializer;
use Phil\ProductFeeds\Serializer\TsvSerializer;
use Phil\ProductFeeds\Shipping;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$rss = new RssSerializer();
$tsv = new TsvSerializer();
$channel = new FeedChannel('Shop', Url::fromString('https://shop.example/'), 'Products');
$link = Url::fromString('https://shop.example/product');
$price = Money::of(1_000, Currency::of('CAD'));
$columns = substr_count($tsv->header($channel), "\t");

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($rss, $tsv, $channel, $link, $price, $columns): void {
    $parts = explode('|', $input, 7);

    try {
        $item = new FeedItem(
            $parts[0],
            $parts[1] ?? 'Title',
            $link,
            $price,
            Availability::InStock,
            description: $parts[2] ?? null,
            brand: $parts[3] ?? null,
            color: $parts[4] ?? null,
            shipping: [new Shipping('CA', $price, $parts[5] ?? null)],
            customLabels: isset($parts[6]) ? [$parts[6]] : [],
        );
    } catch (InvalidFeedItem) {
        return;
    }

    $document = new DOMDocument();

    if (!$document->loadXML($rss->header($channel).$rss->item($item).$rss->footer(), \LIBXML_NONET)) {
        throw new LogicException('An item was written as XML that does not parse.');
    }

    $title = $document->getElementsByTagNameNS(RssSerializer::PRODUCT_NAMESPACE, 'title')->item(0)?->textContent;

    if ($title !== $item->title) {
        throw new LogicException("The title read back is not the item's.");
    }

    $service = $document->getElementsByTagNameNS(RssSerializer::PRODUCT_NAMESPACE, 'service')->item(0)?->textContent;

    if ($service !== $item->shipping[0]->service) {
        throw new LogicException("The shipping service read back is not the item's.");
    }

    $row = $tsv->item($item);

    if (1 !== substr_count($row, "\n") || !str_ends_with($row, "\n") || substr_count($row, "\t") !== $columns || str_contains($row, "\r")) {
        throw new LogicException('An item was written as a TSV row of another shape.');
    }
});
$config->setMaxLen(512);
