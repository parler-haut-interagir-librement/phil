<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use Phil\Url\Url;

/**
 * What a feed says of itself: the store's name, its address and a sentence that describes it. The RSS file writes them
 * in its `channel`; the TSV file has no place for them.
 */
final readonly class FeedChannel
{
    public const int MAX_TITLE = 150;

    public const int MAX_DESCRIPTION = 5_000;

    public string $title;

    public string $description;

    public function __construct(string $title, public Url $link, string $description)
    {
        $this->title = Text::required($title, 'title of a feed', self::MAX_TITLE);
        $this->description = Text::required($description, 'description of a feed', self::MAX_DESCRIPTION, multiline: true);
    }
}
