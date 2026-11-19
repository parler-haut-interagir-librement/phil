# Forms and Twig

## `EventType`

The form of an event, to an `EventDraft`: title, start and end (`datetime-local`), whole days, time zone, the rule —
`phil/recurrence-bundle`'s `RecurrenceRuleType` —, place, page (`https://` added when left out), image, the venue among
the calendar's, by their label, description, tags, separated by commas, and places — the capacity, 1 to 1 000 000, or
empty for no limit. A new event starts in the configured `default_timezone`. Labels come from the `phil_calendar`
domain.

```php
$form = $this->createForm(EventType::class, EventDraft::of($event));
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $calendar->apply($event, $form->getData());
}
```

`EventDraft` carries the validator's constraints: a title of 200 characters at most, a start, an end, an IANA time
zone, a page with a top-level domain.

## Twig

| Name | Kind | What it gives |
|---|---|---|
| `calendar_occurrences(from, through, hostKind, hostReference, tag)` | function | `Calendar::occurrences()` |
| `occurrence\|occurrence_when(locale)` | filter | When an occurrence takes place, in the request's language unless one is given |

```twig
{{ occurrence|occurrence_when }}
{# mardi 10 novembre 2026, 19 h 00 – 22 h 00 #}
{# vendredi 10 juillet 2026 – dimanche 12 juillet 2026 #}
```

One day gives its times, several days their dates and times, whole days their dates alone.

## schema.org

| Function | Gives |
|---|---|
| `calendar_event_schema(event, locale)` | The event as a schema.org `Event`, its name, description and place in the locale — the request's by default —: dates (dates alone when all day), place — its venue, or the place in words —, page, status, keywords and its `Schedule` |
| `calendar_event_json_ld(event, locale)` | The same event as JSON-LD: a node of the page's graph with phil/structured-data-bundle, else its own `<script type="application/ld+json">` element |
| `calendar_texts(events, locale)` | The `EventTextSet` of an event or a list: `title()`, `description()`, `place()` in the locale — see [Translations](translations.md) |
| `calendar_personal(kind, reference, from, through)` | A member's own occurrences of a period — see [Members](members.md#a-members-calendar) |
| `calendar_choices(kind, reference)` | What a member follows and hides, a `MemberChoices` — see [below](#a-members-choices) |
| `calendar_attendance(occurrence)`, `calendar_places_left(occurrence)` | How many attend an occurrence, or an event as a whole; the places left, null without a capacity |
| `calendar_attends(occurrence, kind, reference)` | Whether a member attends it |

With phil/structured-data-bundle installed, `calendar_event_json_ld` prints nothing: it adds the event to the graph of
the page, which `structured_data()` prints in one script element with the site, the page and the other nodes. Render
`structured_data()` after the events, at the end of the body. Without that bundle, each event prints its own element,
whose JSON writes `<`, `>` and `&` as escapes, so that no value can close it.

`calendar_event_schema` gives the data alone, for a template that composes a document of its own.

## A member's choices

`CalendarChoiceType` is the form of one choice, to a `CalendarChoiceDraft`: the source — organizer, tag, venue or event
—, its value, and whether it is followed or hidden, two radio buttons. The draft checks the value against its source;
`CalendarChoices::apply($member, $draft)` keeps it.

```php
$form = $this->createForm(CalendarChoiceType::class);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $choices->apply($member, $form->getData());
}
```

`@PhilCalendar/calendar/_choices.html.twig` draws a member's choices in a card of the PHIL UX kit: those followed, then
those hidden, each a badge. It takes `choices`, from `calendar_choices()`, and three optional variables: `form`, the
view of a `CalendarChoiceType`, drawn in the card's footer; `forget_route`, a route that takes `source` and `value` by
`POST`, for a button beside each badge; and `forget_token`, sent as `_token` with it.

```twig
{{ include('@PhilCalendar/calendar/_choices.html.twig', {
    choices: calendar_choices('member', app.user.id),
    form: form,
    forget_route: 'app_calendar_forget',
    forget_token: csrf_token('calendar_choice'),
}) }}
```

It draws the Card, Badge and Button recipes; the classes `phil-calendar-choices__*` are styled in `calendar.css`.
