<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use LogicException;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventTranslation;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Model\VenueTranslation;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The text of events and venues in a locale: the translation of the first locale of its fallback chain that has one,
 * read by `phil/doctrine-behaviours`, or the event's own text when none has. A list is read in one query per kind of
 * record, never one per event.
 *
 * Without translations — no locale declared — every event is shown in its own text. The locale is the one asked, or
 * the request's, read each time from the translator, so a worker serves each request in its own.
 */
final readonly class EventTexts
{
    public function __construct(
        private ?Translations $translations = null,
        private ?DeclaredLocales $locales = null,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    /**
     * The texts of events, and of their venues, in a locale — the request's when none is given.
     *
     * @param iterable<Event> $events
     */
    public function of(iterable $events, ?string $locale = null): EventTextSet
    {
        $chain = $this->chain($locale ?? $this->current?->getLocale());

        if (!$chain instanceof FallbackChain || !$this->translations instanceof Translations) {
            return new EventTextSet();
        }

        $list = [];
        $venues = [];

        foreach ($events as $event) {
            $list[$event->id] = $event;
            $venue = $event->venue();

            if ($venue instanceof Venue) {
                $venues[$venue->id] = $venue;
            }
        }

        $eventTexts = [];
        $venueNames = [];
        $set = $this->translations->preload($list, $chain);

        foreach ($list as $id => $event) {
            $translation = $set->of($event);

            if ($translation instanceof EventTranslation) {
                $eventTexts[$id] = [$translation->title(), $translation->description()];
            }
        }

        if ([] !== $venues) {
            $set = $this->translations->preload($venues, $chain);

            foreach ($venues as $id => $venue) {
                $translation = $set->of($venue);

                if ($translation instanceof VenueTranslation) {
                    $venueNames[$id] = $translation->name();
                }
            }
        }

        return new EventTextSet($eventTexts, $venueNames);
    }

    /**
     * The translation of an event in exactly a locale, to be written: the stored one, or a new, empty one persisted
     * for the next flush. Nothing is copied from another locale.
     */
    public function translate(Event $event, string $locale): EventTranslation
    {
        $translation = $this->translations()->translate($event, Locale::fromString($locale));
        \assert($translation instanceof EventTranslation);

        return $translation;
    }

    /**
     * The translation of a venue's name in exactly a locale, to be written.
     */
    public function translateVenue(Venue $venue, string $locale): VenueTranslation
    {
        $translation = $this->translations()->translate($venue, Locale::fromString($locale));
        \assert($translation instanceof VenueTranslation);

        return $translation;
    }

    /**
     * The locales an event is translated in, for the alternates of its page.
     *
     * @return list<string>
     */
    public function locales(Event $event): array
    {
        return array_map(static fn (Locale $locale): string => $locale->value, $this->translations?->locales($event) ?? []);
    }

    private function translations(): Translations
    {
        return $this->translations ?? throw new LogicException('The events are translated with phil/doctrine-behaviours and phil_calendar.translations.locales: declare the locales served.');
    }

    /**
     * The chain of a locale the application serves; none for a locale it does not, or without declared locales.
     */
    private function chain(?string $locale): ?FallbackChain
    {
        if (null === $locale || !$this->locales instanceof DeclaredLocales) {
            return null;
        }

        try {
            $locale = Locale::fromString($locale);
        } catch (InvalidLocaleDimension) {
            return null;
        }

        return $this->locales->isServed($locale) ? $this->locales->chainFor($locale) : null;
    }
}
