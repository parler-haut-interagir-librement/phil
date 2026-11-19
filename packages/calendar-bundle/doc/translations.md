# Translations

An event keeps what every locale shares — its times, its rule, its place, its page — and the title and description it
was created with. Its translations live beside it, one row per event and locale, by the translations of
`phil/doctrine-behaviours`: `EventTranslation` holds a title and a description, `VenueTranslation` a venue's name.
The locales, their spelling and their fallback chains are those of `phil/locale-dimensions`.

```yaml
phil_calendar:
    translations:
        locales:
            fr_CA: [fr]     # a reader of Canadian French, then of French
            fr: []
            en: []
```

Without locales, every event is shown in its own text and no translation is read. A chain that names a locale not
served, or one locale twice, stops the build with a message under `phil_calendar.translations.locales`.

## Writing

`EventTexts::translate($event, 'en')` gives the English translation to be written: the stored one, or a new, empty one
persisted for the next flush. Nothing is copied from another locale: a translation nobody wrote never passes for
one. `translateVenue($venue, 'en')` does the same for a venue's name.

```php
$texts->translate($event, 'en')->write('Game night', 'Bring your games.');
$texts->translateVenue($venue, 'en')->rename('Main hall');
$entityManager->flush();
```

`write()` holds a translation to the event's own limits — a title of 1 to 200 characters, a description of 65 535 —;
`rename()` a venue's name to 1 to 200. `locales($event)` gives the locales an event is translated in, for the
alternates of its page.

## Reading

`EventTexts::of($events, $locale)` reads the translations of a list of events and of their venues along the chain of
the locale — the request's, read from the translator each time, when none is given — in one query per kind of record.
The `EventTextSet` it gives has, for each event, `title()`, `description()`, `place()` — its venue's label, or the
place said in words — and `isTranslated()`; `venueName()` and `venueLabel()` for a venue.

**A translation is served whole.** The chain chooses a row, never a field: a French translation with no description is
shown without one, never with the description of another locale. An event with no translation along the chain, or a
locale the application does not serve, is shown in its own text.

The views, the posters, the list and schema.org data read the texts in the request's locale. In Twig,
`calendar_texts(events, locale)` gives the set, and `calendar_event_schema(event, locale)` and
`calendar_event_json_ld(event, locale)` take a locale.

```twig
{% set texts = calendar_texts(events) %}
{% for event in events %}
    <h2>{{ texts.title(event) }}</h2>
{% endfor %}
```

The iCalendar feed has no reader's locale: it gives each event in its own text.

## The tables

| Table | Columns |
|---|---|
| `phil_calendar_event_translation` | `id`, `event` (the event, deleted with it), `locale` (12), `title` (200), `description` (text); unique on `event` and `locale` |
| `phil_calendar_venue_translation` | `id`, `venue` (the venue, deleted with it), `locale` (12), `name` (200); unique on `venue` and `locale` |
