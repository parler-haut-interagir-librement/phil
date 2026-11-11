<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * What the migrations of one run announce, in the order they ran: release notes and news, each in its locales.
 */
final readonly class Release
{
    /**
     * @param list<array<string, string>> $notes each note in its locales
     * @param list<array<string, News>>   $news  each piece of news in its locales
     */
    public function __construct(
        public array $notes = [],
        public array $news = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->notes && [] === $this->news;
    }

    /**
     * The notes written in a locale; a note not written in it is left out.
     *
     * @return list<string>
     */
    public function notesIn(string $locale): array
    {
        return array_column($this->notes, $locale);
    }

    /**
     * The news written in a locale; a piece of news not written in it is left out.
     *
     * @return list<News>
     */
    public function newsIn(string $locale): array
    {
        return array_column($this->news, $locale);
    }
}
