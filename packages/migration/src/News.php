<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * A piece of news a release brings, in one locale: a title, its lines and, maybe, the address that tells more.
 */
final readonly class News
{
    public string $title;

    /**
     * @param list<string> $lines
     */
    public function __construct(
        string $title,
        public array $lines = [],
        public ?string $url = null,
    ) {
        $this->title = trim($title);

        if ('' === $this->title) {
            throw InvalidAnnouncement::because('A piece of news has a title.');
        }

        if ('' === $url) {
            throw InvalidAnnouncement::because('The address of a piece of news is a URL, or none.');
        }
    }
}
