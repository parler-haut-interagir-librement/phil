# Extension points

What an application implements, tags, overrides or relies on. Rules and their occurrences are `phil/recurrence`'s.

## Ports

### `ItemSource`

`Phil\CalendarBundle\Application\View\ItemSource` — what the views show, beside the calendar's events. A service
tagged `phil_calendar.item_source` — by hand: the interface is not autoconfigured — joins `EventItems` and, with
`phil/almanac-bundle`, `AlmanacItems`; `before` or `after` in the tag names the source it goes before or after. See
[Views](views.md#items).

| Method | Contract |
|---|---|
| `items(DateTimeImmutable $from, DateTimeImmutable $until): list<Item>` | The items that touch the period, in any order — `Items` sorts them; reads what it needs once per period, not once per item |

### `HostLabels`

`Phil\CalendarBundle\Application\View\HostLabels` — the application's names of its hosts. A service registered under
this id is used by `CalendarViews`; without one, a host shows as its `kind:reference`.

| Method | Contract |
|---|---|
| `labels(list<string> $hosts): array<string, string>` | A name per host it knows, keyed by `kind:reference`; the others left out |

### `EventTagging`

`Phil\CalendarBundle\Application\EventTagging` — where an event's tags are shared. With `phil/taxonomy-bundle`, it is
`TaxonomyEventTagging`, where an event is the resource `phil_calendar_event`; without it, tags stay free words in the
event's row. See [Events](events.md#tags-in-the-taxonomy).

| Method | Contract |
|---|---|
| `tag(Event $event, list<string> $names): list<string>` | Gives the event these tags; returns the names to keep on it |
| `tagsOf(Event $event): list<string>` | The names of the tags it has |
| `forget(Event $event): void` | Takes its tags away |

### Repositories

`EventRepository`, `VenueRepository`, `AttendanceRepository`, `CalendarChoiceRepository` and
`PersonalFeedTokenRepository` are aliases of their Doctrine adapters; an application points any at its own service,
under the same contracts — `overlapping()` keeps the events that start by the end of a period and whose series has not
ended at its start, of a host when one is given. See [Events](events.md#the-repository).
`EventRepository::proposalsOf()` and `forgetProposer()`, and `AttendanceRepository::forgetMember()`, serve a member's
export and erasure; `CalendarChoiceRepository` keeps one row per member, source and value;
`PersonalFeedTokenRepository::replace()` keeps one token per member and finds it by its digest, never by the secret.

### `AttendancePolicy`

`Phil\CalendarBundle\Application\AttendancePolicy` — whether the application admits a member to an event, asked
once the calendar's own checks pass. Registered under its interface, `Attendees` takes it. See
[Attendees](members.md#paid-attendance).

| Method | Contract |
|---|---|
| `admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool` | True to admit; the occurrence's start, or null for every occurrence. Paid attendance answers once the order is paid |

### The privacy of members

With `phil/privacy-bundle`, `CalendarPrivacy` is tagged `phil_privacy.exporter` and `phil_privacy.eraser`; the data
subject is the reference of a member of the kind `privacy_member_kind`. See [Members](members.md#privacy).

### The workflow of proposals

`phil_calendar_proposal`, a state machine of `symfony/workflow` dispatched through the application's event dispatcher:
a guard holds an approval or a refusal, a listener follows them. See [Proposals](members.md#proposals).

## Configuration

| Option | What it extends |
|---|---|
| `budget.occurrences`, `budget.expansions` | What one request unrolls at most — see [the budget](events.md#the-budget-of-a-request) |
| `default_timezone` | The time zone of a new event, and of the periods and dates of the views |
| `first_weekday` | The day weeks start on in the views |
| `kit_subdirectory` | Where the recipes of the PHIL UX kit are installed, when the Symfony UX Toolkit is not enabled to say it |
| `name` | The name of the iCalendar feeds, the calendar's and the members' |
| `privacy_member_kind` | The kind of member a data subject of `phil/privacy-bundle` is |
| `translations.locales` | The locales events and venues are shown in, and their fallback chains |

## Templates, styles and words

| Point | How |
|---|---|
| The component | `<twig:PhilCalendar:Calendar />` and its props: views, shared resources, hours, lanes — see [Views](views.md#the-component) |
| The templates | `components/Calendar.html.twig` and `calendar/_toolbar`, `_month`, `_time`, `_list`, `_posters`, `_item`, `_choices` in `@PhilCalendar`, overridden as any bundle's |
| The recipes | The Popover, Badge, Card, Button, ButtonGroup and Empty recipes, installed in the application and changed there, or taken from the Shadcn kit, which has the same names, props and blocks |
| The look | `assets/styles/calendar.css`, in the `phil.components` layer so the application's styles win; `--phil-calendar-event`, `--phil-calendar-almanac`, `--phil-calendar-hour-height` |
| Dates in templates | `calendar_title`, `calendar_format`, `calendar_time`, `calendar_hour`, `calendar_days`, `calendar_link` |
| The event form | `EventType`, extended by a form type extension of the application's — see [Forms and Twig](forms-twig.md) |
| A member's choices | `CalendarChoiceType` and `_choices.html.twig`, under a route of the application's that knows who is signed in — see [Forms and Twig](forms-twig.md#a-members-choices) |
| The budget | `ExpansionBudget`, a service reset by `kernel.reset`; a command resets it between its units of work |

## Outside the bundle

| What | Where |
|---|---|
| Ticketing — selling the places of an occurrence | An application's `AttendancePolicy`, and the commerce packages it relies on; the bundle takes no payment |
| Federation — events followed from other servers, ActivityPub | A package of its own, `calendar-activitypub`, once a shared ActivityPub building block exists; the bundle does not change for it |
| Synchronising with members' calendars — CalDAV, Google, Microsoft | `phil/calendar-sync` |
| "Add to calendar" links of an occurrence to the calendars of Google, Outlook.com or Office 365 | Symfony UX CalendarLink once it is stable; the iCalendar feeds stay the bundle's |
