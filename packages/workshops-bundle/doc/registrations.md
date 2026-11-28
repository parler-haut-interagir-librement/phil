# Sessions and registrations

`Phil\WorkshopsBundle\Application\Workshops` is the service an application calls. Each method takes and returns
identifiers and entities; none reads a request or a user.

## Workshops and sessions

```php
$workshop = $workshops->createWorkshop('Painting miniatures');
$session = $workshops->schedule($workshop->id, $start, $end, 'America/Toronto', capacity: 8, opensAt: $opens, closesAt: $closes);
```

| Value | Bounds |
|---|---|
| A workshop's title | 1 to 200 characters on one line, trimmed |
| A session's span | Its end after its start |
| A session's time zone | A name PHP knows, at most 64 bytes; the session's moments come back on its clock |
| A session's capacity | 1 to 100,000 seats |
| The registration window | Opens before it closes; closes at the latest when the session ends |

A value out of bounds throws `Domain\Exception\InvalidWorkshop`. An inactive workshop — `Workshop::activate(false)` —
takes no new session; the sessions already scheduled go on.

| Method | What it does |
|---|---|
| `reschedule($sessionId, $start, $end)` | Moves a session; its registrations are kept. The window must still fit |
| `openRegistration($sessionId, $opensAt, $closesAt)` | Changes the window; `null` opens at once, or closes when the session starts |
| `resize($sessionId, $capacity)` | Changes the capacity, never below the seats taken and offered; the waiting list, when there is one, offers the seats it frees |
| `cancelSession($sessionId)` | Cancels a session for good: it takes no more registrations, its waiting list closes, and its registrations stay for the application to tell their participants |

The moments are kept in UTC, in Doctrine's `datetime_utc_immutable` columns, whatever the zone of the process.

## Registering

```php
$registration = $workshops->register($session->id, $accountId, seats: 2);
```

The participant is an identifier the application chooses — an account, an order, a ticket —, 1 to 255 bytes. An
e-mail address is refused: it changes and identifies a person. A participant already registered in the session gets
that registration back, unchanged, without an event: a form sent twice registers once. Once cancelled, the participant
may register again.

The session decides in this order, and the first reason that applies is the refusal, a
`Application\Exception\RegistrationRefused` with its `RefusalReason`:

| Reason | When |
|---|---|
| `cancelled` | The session is cancelled |
| `not_open` | Before the window opens |
| `closed` | From the moment the window closes, or from the start of the session when it has no closing moment |
| `too_many_seats` | More seats than `max_seats`, or than the session holds; the parameter `max` is the most it takes |
| `full` | Not enough seats free that nobody claims |

When the session takes it, the registration is **confirmed**. The seats it takes are those free that the session's
waiting list does not claim: the places offered and not answered yet, and, while somebody waits, the places those
waiting ask for. Nobody skips the line: a single who registers while a group of four waits for four seats is refused as
`full`, even when one seat is free, and may join the waiting list behind the group.

`seats($sessionId)` gives a `SeatCount`: `capacity`, `taken` by the confirmed registrations, `free`, `claimed` by the
waiting list, and `available` to a new registration; `isFull()` says whether none is.

## The waiting list

With phil/waitlist-bundle installed and enabled, and the `waitlist` option on, each session has a waiting list there,
opened when the first participant joins it: its subject is `phil_workshops.session` and the session's identifier.
`Integration\Waitlist\SessionWaitlists` is the service to call:

```php
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;

try {
    $registration = $workshops->register($session->id, $accountId, seats: 2);
} catch (RegistrationRefused $refused) {
    if (RefusalReason::Full !== $refused->reason) {
        throw $refused;
    }

    $entry = $sessionWaitlists->join($session->id, $accountId, seats: 2); // phil/waitlist-bundle's Entry
}

// Later, the participant answers the offer the list made:
$registration = $sessionWaitlists->accept($entry->id);
```

| Method | What it does |
|---|---|
| `join($sessionId, $participant, $seats)` | Puts the participant on the session's list, behind those already in it; a participant already waiting or holding an offer gets that entry back |
| `accept($entryId)` | Accepts the offer the entry holds and confirms the participant's registration for the places offered, in one transaction |
| `of($sessionId)` | The session's list, once somebody joined it |
| `balance($sessionId)` | Gives the list the free seats nobody took or was offered, up to those its waiting participants ask for, and takes back the rest |
| `balanceList($waitlistId)` | The same, given the list; a list that is not a session's is left alone |
| `close($sessionId)` | Closes the list: no more entries; the offers held stay until answered or lapsed |

`join()` refuses as registering does — `cancelled`, `not_open`, `closed`, `too_many_seats` — and with `registered`
when the participant already holds seats in the session. The list's own refusals, such as a closed list, come as
phil/waitlist-bundle's `WaitlistRefused`, and a participant it does not take as an identifier as its `InvalidWaitlist`.

`accept()` refuses an entry that holds no offer, or whose deadline has come, with phil/waitlist-bundle's
`WaitlistRefused`; a session that no longer takes registrations with its `RefusalReason`; and seats taken meanwhile by
a registration made past the service as `full`. A participant who holds seats in the session already gets that
registration back, and their entry leaves the list. The registration confirmed dispatches `RegistrationConfirmed`, as
one made by `register()` does.

The seats move between the session and its list on their own: the listener balances the list when a registration is
cancelled, when the capacity changes and when an entry leaves, and closes it when the session is cancelled. Each
balance takes back the places the list holds and releases its share again, so that the list's policy runs on the
entries waiting now: with `first_in_line`, a group of four keeps its turn while two seats are held for it, and the
seats go to the next entry once the group leaves. The list offers places while the session takes registrations: once
its window has closed, freed seats stay with the session.

Accepting, declining, lapsing and leaving are phil/waitlist-bundle's: `Waitlists::decline()`, `leave()`,
`lapseExpired()` and the `phil:waitlist:lapse` command, whose freed places it offers to the next entries itself. Accept
through `SessionWaitlists::accept()` rather than `Waitlists::accept()`, which gives the places to nobody.

Without phil/waitlist-bundle, or with `waitlist: false`, a full session refuses as `full` and nothing claims a free
seat.

## Cancelling

```php
$workshops->cancel($registrationId);
```

A confirmed registration is cancelled once, with its `cancelledAt` moment; a second cancellation throws
`InvalidWorkshop`. Its seats go to the session's waiting list when somebody waits, and to the next registration
otherwise.

## The lock

`register()`, `cancel()`, `resize()` and `cancelSession()`, and `SessionWaitlists::join()`, `accept()` and
`balance()`, run under the session's lock:
`RegistrationRepository::exclusively()` opens a transaction that first updates the session's row, then reads the
session again. A second request on the same session waits for the first to commit, then sees its seats: two requests
never take the last seat together, and a capacity changed meanwhile is the one counted. A refusal rolls the
transaction back.

`SessionWaitlists` takes the session's lock first, then, through phil/waitlist-bundle, the list's lock inside the same
transaction: always in that order, so that two requests never wait for each other. The events of the list that a call
causes, such as `PlaceOffered`, go out inside that transaction, before it commits.

## Events

Each change dispatches an event once it is saved, through the `event_dispatcher` service when there is one. Each
carries identifiers only; a listener reads the rest through the repositories. The waiting list's own events —
`EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferLapsed`… — are phil/waitlist-bundle's, about its entries.

| Event | Carries | When |
|---|---|---|
| `RegistrationConfirmed` | `registrationId`, `sessionId` | A registration takes its seats: registered, or an offer accepted |
| `RegistrationCancelled` | `registrationId`, `sessionId` | A registration is cancelled, before the waiting list offers the seats it frees |
| `SessionResized` | `sessionId` | The capacity changes, before the waiting list offers or takes back seats |
| `SessionRescheduled` | `sessionId` | A session moves |
| `SessionCancelled` | `sessionId`, `workshopId` | A session is cancelled |

## Messages

`Infrastructure\Translation\RefusalMessages` gives each refusal and each status in the reader's language, from the
messages of the `phil_workshops` domain, in English and French:

```php
$messages->message($refused);                          // "From 1 to 4 seats can be taken per registration."
$messages->status(RegistrationStatus::Cancelled, 'fr'); // "Annulée"
```
