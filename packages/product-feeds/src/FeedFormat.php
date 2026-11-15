<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use Phil\ProductFeeds\Contract\FeedSerializer;
use Phil\ProductFeeds\Serializer\RssSerializer;
use Phil\ProductFeeds\Serializer\TsvSerializer;

/**
 * The two file formats the package writes: RSS 2.0 with the `g:` namespace of product data, and tab-separated values
 * with one header row.
 */
enum FeedFormat: string
{
    case Xml = 'xml';
    case Tsv = 'tsv';

    public function serializer(): FeedSerializer
    {
        return match ($this) {
            self::Xml => new RssSerializer(),
            self::Tsv => new TsvSerializer(),
        };
    }

    /**
     * The extension of a file in this format, without its dot.
     */
    public function extension(): string
    {
        return $this->value;
    }

    /**
     * The media type a server sends the file with.
     */
    public function mediaType(): string
    {
        return match ($this) {
            self::Xml => 'application/rss+xml; charset=UTF-8',
            self::Tsv => 'text/tab-separated-values; charset=UTF-8',
        };
    }
}
