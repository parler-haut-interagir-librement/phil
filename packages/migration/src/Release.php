<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * What the migrations of one run announce, in the order they ran: release notes and news, each in its locales.
 *
 * A note or a piece of news that several migrations bring is told once in a locale, where it first comes: two notes
 * are the same when they only differ by the spaces around them, two pieces of news when their title, their lines so
 * compared and their address are.
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
     * The notes written in a locale, each once; a note not written in it is left out.
     *
     * @return list<string>
     */
    public function notesIn(string $locale): array
    {
        $notes = [];

        foreach (array_column($this->notes, $locale) as $note) {
            $notes[trim($note)] ??= $note;
        }

        return array_values($notes);
    }

    /**
     * The news written in a locale, each piece once; a piece of news not written in it is left out.
     *
     * @return list<News>
     */
    public function newsIn(string $locale): array
    {
        $news = [];
        $seen = [];

        foreach (array_column($this->news, $locale) as $piece) {
            $compared = [$piece->title, array_map(trim(...), $piece->lines), $piece->url];

            if (!\in_array($compared, $seen, true)) {
                $seen[] = $compared;
                $news[] = $piece;
            }
        }

        return $news;
    }
}
