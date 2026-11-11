<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * The notes and news the migrations leave as they run, kept by the migration that left them until the run is over:
 * then only those of the migrations that ran to the end make the release.
 */
final class Announcements
{
    /** @var array<string, list<array<string, string>>> */
    private array $notes = [];

    /** @var array<string, list<array<string, News>>> */
    private array $news = [];

    /**
     * A release note of a migration, in its locales: `['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']`.
     *
     * @param array<string, string> $note
     */
    public function note(string $migration, array $note): void
    {
        foreach ($this->checked($note) as $line) {
            if ('' === trim($line)) {
                throw InvalidAnnouncement::because('A release note is a line of text.');
            }
        }

        $this->notes[$migration][] = $note;
    }

    /**
     * A piece of news a migration brings, in its locales.
     *
     * @param array<string, News> $news
     */
    public function news(string $migration, array $news): void
    {
        $this->news[$migration][] = $this->checked($news);
    }

    /**
     * The release of the migrations given, in their order, then nothing more is kept.
     *
     * @param list<string> $migrations
     */
    public function release(array $migrations): Release
    {
        $notes = [];
        $news = [];

        foreach ($migrations as $migration) {
            array_push($notes, ...$this->notes[$migration] ?? []);
            array_push($news, ...$this->news[$migration] ?? []);
        }

        $this->clear();

        return new Release($notes, $news);
    }

    /**
     * Forgets everything kept, as when a run announces nothing.
     */
    public function clear(): void
    {
        $this->notes = [];
        $this->news = [];
    }

    /**
     * @template T
     *
     * @param array<string, T> $byLocale
     *
     * @return array<string, T>
     */
    private function checked(array $byLocale): array
    {
        if ([] === $byLocale) {
            throw InvalidAnnouncement::because('An announcement is written in one locale at least.');
        }

        foreach (array_keys($byLocale) as $locale) {
            Locale::check($locale);
        }

        return $byLocale;
    }
}
