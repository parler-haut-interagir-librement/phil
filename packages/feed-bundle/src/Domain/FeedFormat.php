<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain;

/**
 * The formats a feed is written in, each with its media type and the name readers know it by.
 */
enum FeedFormat: string
{
    case Atom = 'atom';
    case Rss = 'rss';
    case Json = 'json';

    public function mediaType(): string
    {
        return match ($this) {
            self::Atom => 'application/atom+xml',
            self::Rss => 'application/rss+xml',
            self::Json => 'application/feed+json',
        };
    }

    /**
     * The `Content-Type` of an answer: the media type, in UTF-8.
     */
    public function contentType(): string
    {
        return $this->mediaType().'; charset=UTF-8';
    }

    public function label(): string
    {
        return match ($this) {
            self::Atom => 'Atom',
            self::Rss => 'RSS',
            self::Json => 'JSON Feed',
        };
    }
}
