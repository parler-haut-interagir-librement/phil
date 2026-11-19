# Attendees, proposals and members' calendars

A member is a person of the application, named as hosts are: a kind and a reference, `Member::of('member', '42')`,
kept as `member:42`. The calendar knows nothing more of them: who they are and what they may do is the application's
— its access control decides who may sign up, propose, or approve.

## Attendees

`Attendees` signs members up for an occurrence, or for every occurrence by attending the event itself.

```php
$attendees->attend($occurrence, Member::of('member', '42'));   // one date
$attendees->attend($event, Member::of('member', '7'));         // every date
$attendees->count($occurrence);                                // both kinds
$attendees->placesLeft($occurrence);                           // null without a capacity
$attendees->attendees($occurrence, limit: 100, offset: 0);     // in the order they came
$attendees->withdraw($occurrence, Member::of('member', '42'));
```

An event has a capacity, `Event::limitTo()` or the form's *Places*: how many people can attend each occurrence, 1 to
1 000 000, or none for no limit. Those of an occurrence and those of every occurrence count against it. Signing up
again gives the attendance kept. A list of attendees is read a page at a time, 500 at most, with DBAL: an event of
thousands loads only the names a page shows.

An attendance is refused with `AttendanceRefused`, whose `reason` says why:

| Reason | When |
|---|---|
| `cancelled` | The event is cancelled |
| `not_published` | The event is a proposal not approved, or a refused one |
| `not_an_occurrence` | No occurrence of the event starts at that moment, or it was taken away |
| `full` | The capacity is reached |
| `policy` | The application's `AttendancePolicy` does not admit it |

The capacity is read before the row is written: two members taking the last place at the same moment may both get
it. An application that sells its places holds them in its policy, under a lock of its own.

### Paid attendance

The calendar takes no payment. An `AttendancePolicy`, registered under its interface, is asked last, once the
calendar's own checks pass: a policy of the application's admits a member once their order is paid, their ticket
held or their membership current.

```php
final readonly class PaidAttendance implements AttendancePolicy
{
    public function admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool
    {
        return $this->orders->hasPaid($member->reference, 'event:'.$event->id);
    }
}
```

## A member's calendar

`PersonalCalendar::occurrences($member, $from, $through)` gives a member's own occurrences of a period, in order, each
once:

- those they attend — the dates they signed up for, every date of an event they attend;
- those of the events they host, when the application names them as a host by the same kind and reference;
  `hosted: false` leaves them out;
- those of the sources they follow — a host's calendar, a tag, a venue, an event —, less those they hide.

What a member attends or hosts is always there: hiding a source only takes away what following brought, and hiding
wins over following. The period is read once, its hosts in one query, and its occurrences count against the request's
[budget](events.md#the-budget-of-a-request). In Twig, `calendar_personal(kind, reference, from, through)`; on a page,
the component with a `member` ([Views](views.md#the-component)).

### Following and hiding

`CalendarChoices` keeps what each member follows and hides, `MAX` (200) choices at most, in the table
`phil_calendar_choice`, the member by their `kind:reference`, when they chose as a moment in UTC.

```php
$choices->follow($member, ChoiceSource::Host, 'association:42');   // a host's calendar
$choices->follow($member, ChoiceSource::Tag, 'larp');
$choices->hide($member, ChoiceSource::Venue, $venue->id);
$choices->hide($member, ChoiceSource::Event, $event->id);
$choices->forget($member, ChoiceSource::Tag, 'larp');              // neither followed nor hidden
$choices->of($member);                                               // MemberChoices
```

| Source | Value |
|---|---|
| `Host` | `kind:reference`, each 1 to 64 letters, digits, `_`, `.`, `:` or `-` |
| `Tag` | 1 to 100 characters, no control character |
| `Venue`, `Event` | The identifier, a UUID of 36 lowercase characters |

A value its source does not name, or a choice past 200, is an `InvalidChoice`. Following a source hidden shows it again,
and hiding one followed changes the same choice. `MemberChoices` lists them — `followed($source)`, `hidden($source)` —
and tells whether an event is followed or hidden, by itself, its venue, one of its hosts or one of its tags.

The form is `CalendarChoiceType`, to a `CalendarChoiceDraft` that `CalendarChoices::apply($member, $draft)` keeps; the
template `@PhilCalendar/calendar/_choices.html.twig` lists a member's choices with the recipes of the kit — see
[Forms and Twig](forms-twig.md#a-members-choices).

### The feed of a member

`PersonalFeeds` gives each member one secret, the only key to the address of their own iCalendar feed:

```php
$secret = $feeds->issue($member);    // shown once; a new one revokes the one before
$url = $urls->generate('phil_calendar_personal_ics', ['token' => $secret], UrlGeneratorInterface::ABSOLUTE_URL);
$feeds->revoke($member);             // the address answers no more
$feeds->issuedAt($member);           // in UTC, or null
```

A secret is 32 random bytes written in 64 hexadecimal digits. Only its SHA-256 digest is kept, in
`phil_calendar_feed_token`: the address cannot be read back from the database, so a page shows it when it is issued
and offers a new one after. The feed holds the member's calendar from a month ago to a year ahead, each occurrence a
`VEVENT` of its own — see [The feed](feeds.md#a-members-feed).

### Privacy

With `phil/privacy-bundle` installed, `CalendarPrivacy` joins the export and the erasure of a person. The data subject
is the reference of a member of the kind `privacy_member_kind`, `member` by default: the subject `42` is `member:42`.
The export lists their choices, when their feed's secret was issued — never the secret —, what they attend and the
events they proposed. The erasure deletes their choices, their secret and their attendances, and the events they
proposed no longer name them; the events stay. Run again, it finds nothing more and reports the same.

## Proposals

A member proposes an event; a moderator approves or refuses it. `Proposals::propose($draft, $member)` makes the event
from a draft as any other, kept out of the calendar — its views, its feed, its occurrences — until it is approved.

```php
$event = $proposals->propose($draft, Member::of('member', '42'));
$proposals->waiting();          // the proposals by their start, 100 by default, 500 at most
$proposals->approve($event);    // published
$proposals->refuse($event);     // refused; it can be approved later
```

The states and transitions are the state machine `phil_calendar_proposal` of `symfony/workflow`, defined by
`ProposalWorkflow`:

| Transition | From | To |
|---|---|---|
| `approve` | `proposed`, `refused` | `published` |
| `refuse` | `proposed` | `refused` |

The workflow's events go through the application's event dispatcher: a guard on
`workflow.phil_calendar_proposal.guard.approve` holds an approval, a listener on
`workflow.phil_calendar_proposal.completed.approve` tells the member. A transition that is not enabled is a
`NotEnabledTransitionException`; `can($event, 'approve')` asks first. `Event::proposedBy()` names the member.
