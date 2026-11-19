# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `EditorialCalendar` | The planned items and the publication batches, immutable, with batches applied whole |
| `PlannedItem` | A planned publication: content, language, author, section, state, moment in UTC |
| `ItemState` | Idea, drafting, ready, scheduled, published, withdrawn, and the workflow between them |
| `AuthorId` | The stable identifier of an author, never an email address |
| `Filter` | Which items a view or a list shows, by author, section and state |
| `PublicationBatch` | Items published together, at a moment or by hand |
| `View\CalendarView` | Day, week, month and list views in a time zone |
| `View\Period` | What a view shows: its range, its entries, its days |
| `View\Day` | One day of a view: its local date, its UTC bounds, its entries |
| `View\Entry` | One line of a view: an item, a batch with its items, or a transition |
| `View\EntryKind` | Batch, item or transition, in the order entries of one moment are listed |
| `Contract\TransitionReader` | The port through which the calendar reads transitions planned elsewhere |
| `Transition\PlannedTransition` | A transition planned for a resource at a moment |
| `Transition\NoTransitions` | The reader of a calendar that shows its own items only |
| `Review\Review` | Finds repeated content, unready items, publications too close and gaps |
| `Review\ReviewRules` | The minimum spacing and the maximum gap |
| `Review\Issue` | A conflict or a gap: kind, moments, items |
| `Review\IssueKind` | Duplicate content, not ready, too close, gap |
| `ICalendar\ICalendarWriter` | Writes the publications as an iCalendar stream |
| `Exception\EditorialCalendarException` | Every exception the package throws |
| `Exception\InvalidItem` | An item, author, batch, rule or view built from values it refuses |
| `Exception\InvalidTransition` | A change of state the workflow refuses, or a scheduled state without a moment |
| `Exception\UnknownItem` | An item or a batch the calendar does not hold |
| `Exception\BatchFailed` | A batch not applied because one of its items refused; none was applied |

`Moment` is the package's own and may change in any version.
