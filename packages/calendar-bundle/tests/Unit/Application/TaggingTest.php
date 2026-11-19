<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\RecordedTagging;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calendar::class)]
final class TaggingTest extends TestCase
{
    public function testAnEventKeepsTheNamesOfTheTaxonomy(): void
    {
        $tagging = new RecordedTagging();
        $event = new Calendar(new InMemoryEvents(), $tagging)->schedule($this->draft('Soirée', [' jeux ', '', 'jeux', 'soirée']));

        self::assertSame(['JEUX', 'SOIRÉE'], $event->tags());
        self::assertSame(['JEUX', 'SOIRÉE'], $tagging->tags[$event->id]);
    }

    public function testWithoutTaxonomyAnEventKeepsItsOwnNames(): void
    {
        $event = new Calendar(new InMemoryEvents())->schedule($this->draft('Soirée', ['jeux']));

        self::assertSame(['jeux'], $event->tags());
    }

    public function testARemovedEventLeavesTheTaxonomy(): void
    {
        $events = new InMemoryEvents();
        $tagging = new RecordedTagging();
        $calendar = new Calendar($events, $tagging);
        $event = $calendar->schedule($this->draft('Soirée', ['jeux']));
        $calendar->remove($event);

        self::assertSame([], $events->events);
        self::assertSame([], $tagging->tags);

        $alone = new Calendar($events);
        $alone->remove($alone->schedule($this->draft('Atelier', ['peinture'])));
        self::assertSame([], $events->events);
    }

    public function testTheEventsTakeTheNamesTheTaxonomyGivesThemNow(): void
    {
        $events = new InMemoryEvents();
        $tagging = new RecordedTagging();
        $calendar = new Calendar($events, $tagging);
        $renamed = $calendar->schedule($this->draft('Soirée', ['jeux']));
        $same = $calendar->schedule($this->draft('Atelier', ['peinture']));
        $tagging->tags[$renamed->id] = ['JDR'];
        $events->saved = [];

        self::assertSame(1, $calendar->refreshTags());
        self::assertSame([$renamed->id], $events->saved, 'Only the changed event is saved again.');
        self::assertSame([['JDR'], ['PEINTURE']], [$renamed->tags(), $same->tags()]);
        self::assertSame(0, $calendar->refreshTags());
        self::assertSame(0, new Calendar($events)->refreshTags());

        $before = new Calendar($events)->schedule($this->draft('Avant', ['ancien']));
        $events->saved = [];
        self::assertSame(1, $calendar->refreshTags(), 'tagged before the taxonomy came');
        self::assertSame([['ANCIEN'], ['ANCIEN']], [$before->tags(), $tagging->tags[$before->id]]);
        self::assertSame([$before->id], $events->saved);
    }

    public function testTheEventIsSavedBeforeTheTaxonomyAndAgainOnlyWhenItRenames(): void
    {
        $events = new InMemoryEvents();
        $tagging = new RecordedTagging();
        $calendar = new Calendar($events, $tagging);
        $renamed = $calendar->schedule($this->draft('Soirée', ['jeux']));
        $same = $calendar->schedule($this->draft('Atelier', ['JEUX']));

        self::assertSame([$renamed->id, $renamed->id, $same->id], $events->saved);
    }

    /**
     * @param list<string> $tags
     */
    private function draft(string $title, array $tags): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('UTC'));
        $draft->end = new DateTimeImmutable('2026-11-10 22:00', new DateTimeZone('UTC'));
        $draft->timezone = 'America/Toronto';
        $draft->tags = $tags;

        return $draft;
    }
}
