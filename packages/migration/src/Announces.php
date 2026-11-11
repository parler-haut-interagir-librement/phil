<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * What an `Announcing` migration needs: the announcements of the run, and the methods that add to them.
 *
 * @phpstan-require-implements Announcing
 */
trait Announces
{
    private ?Announcements $announcements = null;

    public function announceWith(Announcements $announcements): void
    {
        $this->announcements = $announcements;
    }

    /**
     * A release note, in its locales: `['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']`.
     *
     * @param array<string, string> $note
     */
    protected function note(array $note): void
    {
        $this->announcements()->note(static::class, $note);
    }

    /**
     * A piece of news, in its locales.
     *
     * @param array<string, News> $news
     */
    protected function news(array $news): void
    {
        $this->announcements()->news(static::class, $news);
    }

    private function announcements(): Announcements
    {
        return $this->announcements ?? throw AnnouncementsMissing::in(static::class);
    }
}
