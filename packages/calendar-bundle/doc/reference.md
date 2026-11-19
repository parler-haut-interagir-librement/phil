# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilCalendarBundle` | `final class`, `AbstractBundle` | Alias `phil_calendar`; the configuration, the mapping, the services |
| `Domain\Model\Event` | entity | An event, its rule, dates added, exceptions and tags; its occurrences |
| `Domain\Model\EventHost` | entity | Who holds an event |
| `Domain\Model\Venue` | entity | Where events take place: a place of its own or a listing of the directory |
| `Domain\ValueObject\Occurrence` | `final readonly class` | One occurrence of an event |
| `Domain\ValueObject\Member` | `final readonly class` | A person of the application, `kind:reference` |
| `Domain\Model\EventTranslation`, `VenueTranslation` | entity | An event's title and description, a venue's name, in one locale |
| `Domain\Model\Attendance` | entity | A member who attends an occurrence, or every one |
| `Domain\Exception\AttendanceRefused` | exception | An attendance refused, and its `reason` |
| `Domain\Repository\AttendanceRepository` | interface | The port of attendances |
| `Domain\Model\CalendarChoice`, `Domain\Choice\ChoiceSource` | entity, enum | A source a member follows or hides: a host, a tag, a venue, an event |
| `Domain\Model\PersonalFeedToken` | entity | The digest of a member's feed secret |
| `Domain\Repository\CalendarChoiceRepository`, `PersonalFeedTokenRepository` | interface | The ports of choices and feed secrets |
| `Domain\Exception\InvalidChoice` | exception | A choice that cannot be |
| `Application\EventTexts`, `EventTextSet` | `final readonly class` | Events and venues in a locale: written, and read for a list |
| `Application\Attendees`, `AttendancePolicy` | `final readonly class`, interface | Sign up, withdraw, count, list; the application's admission |
| `Application\PersonalCalendar` | `final readonly class` | A member's own calendar: attended, hosted, followed, less what is hidden |
| `Application\CalendarChoices`, `MemberChoices`, `CalendarChoiceDraft` | `final readonly class`, value, `final class` | What members follow and hide; a member's choices read once; a choice as a form fills it |
| `Application\PersonalFeeds` | `final readonly class` | The secrets of members' feeds: issued, revoked, read |
| `Application\ExpansionBudget` | `final class` | What one request unrolls at most, shared by every call, reset between requests |
| `Application\Proposals`, `ProposalWorkflow` | `final readonly class`, `final class` | Members' proposals, and their state machine `phil_calendar_proposal` |
| `Application\PrintableCalendar` | `final readonly class` | The calendar printed, a month per page, by `phil/paged-media` |
| `Domain\Exception\InvalidEvent` | exception | An event that cannot be |
| `Domain\Repository\EventRepository`, `VenueRepository` | interface | The ports |
| `Application\Calendar` | `final readonly class` | Schedule, change, remove, host, the occurrences of a period; refresh the tags |
| `Application\EventTagging` | interface | The port to a taxonomy that holds the events' tags |
| `Application\EventDraft`, `VenueDraft` | `final class` | An event, a venue, as a form fills it |
| `Application\Venues` | `final readonly class` | Create, change, remove and list venues; the venue of a listing |
| `Application\ICalendar` | `final class` | Events, or occurrences on their own, as an iCalendar file |
| `Application\View\CalendarView`, `Period` | enum, `final readonly class` | The views, and the period one shows |
| `Application\View\Item`, `ItemSource`, `EventItems`, `AlmanacItems`, `Items` | value, interface, sources | What a view shows, and where it comes from |
| `Application\View\MonthGrid`, `MonthWeek`, `Bar`, `Lanes` | layout | The month's grid |
| `Application\View\TimeGrid`, `TimeColumn`, `TimedBlock`, `Resources`, `CalendarResource` | layout | The week and the day by the hour, shared or not |
| `Application\View\Agenda`, `AgendaGroup`, `DaySpan` | layout | The list grouped by days, and days in words |
| `Application\View\CalendarViews`, `HostLabels` | service, port | The views of a page; the application's names of its hosts |
| `Application\View\AlmanacImport` | `final readonly class` | The almanac's dates of a year made events, once |
| `Infrastructure\Doctrine\DoctrineEventRepository`, `DoctrineVenueRepository`, `DoctrineAttendanceRepository`, `DoctrineCalendarChoiceRepository`, `DoctrinePersonalFeedTokenRepository` | repository | The ports on Doctrine ORM; attendees counted and listed, a member's rows counted and forgotten, with DBAL |
| `Infrastructure\Form\EventType`, `VenueType`, `CalendarChoiceType`, `TagsTransformer` | form types, data transformer | The event, venue and choice forms |
| `Infrastructure\Http\CalendarFeedController` | controller | `calendar.ics` |
| `Infrastructure\Http\ICalendarResponse` | response | An iCalendar file |
| `Infrastructure\Http\PersonalFeedController` | controller | `personal/{token}.ics`, a member's own feed |
| `Infrastructure\Privacy\CalendarPrivacy` | adapter | A member's part of an export and an erasure of `phil/privacy-bundle` |
| `Infrastructure\Twig\CalendarExtension` | Twig extension | `calendar_occurrences`, `occurrence_when`, `calendar_event_schema`, `calendar_event_json_ld`, `calendar_texts`, `calendar_personal`, `calendar_choices`, `calendar_attendance`, `calendar_places_left`, `calendar_attends`, `calendar_days`, `calendar_title`, `calendar_format`, `calendar_time`, `calendar_hour`, `calendar_link`, `phil_calendar_kit` |
| `Infrastructure\Twig\KitComponents` | service | The names under which the recipes of the PHIL UX kit are installed |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | compiler pass | Reads the components subdirectory of the Symfony UX Toolkit when `kit_subdirectory` is not set |
| `Infrastructure\Twig\Components\CalendarComponent` | Live Component | `PhilCalendar:Calendar` |
| `Infrastructure\Console\ImportAlmanacCommand` | console command | `phil:calendar:import-almanac` |
| `Infrastructure\Taxonomy\TaxonomyEventTagging` | adapter | The events' tags in `phil/taxonomy-bundle`, resource `phil_calendar_event` |
| `Infrastructure\Console\RefreshTagsCommand` | console command | `phil:calendar:refresh-tags` |
| `templates/components/Calendar.html.twig`, `templates/calendar/*` | templates | The component and its views; a member's choices, `_choices` |
| `assets/src/calendar_controller.js` | Stimulus controller | The keyboard in the month's grid; `moved()` |
| `assets/styles/calendar.css` | stylesheet | The views' look, on PHIL UX's tokens |
