# Waiting lists

Everything goes through the `Phil\WaitlistBundle\Application\Waitlists` service.

## Opening a list

A list belongs to a subject the application names: a kind — lowercase letters, digits, `_`, `.` and `-`, such as
`session` or `event.ticket` — and an identifier. A subject has one list: `open()` creates it on its first call and gives
the same list back afterwards, unchanged.

```php
$waitlist = $waitlists->open('session', $session->id);
$waitlist = $waitlists->open('workshop', $workshop->id, offerWindow: 3600);
$waitlist = $waitlists->open('convention', $convention->id, WaitlistMode::Draw);

$waitlists->ofSubject('session', $session->id); // the list, or null
```

The table holds one list per subject: when two requests open the same subject's list at once, the second fails on that
unique constraint and finds the list by calling `open()` again.

The list never counts the subject's capacity: the application does, and tells the list when places free up. When the
list opens is the application's choice too — when the last place is taken, or from the start.

## Joining and the position

```php
$entry = $waitlists->join($waitlist->id, $accountId, places: 2);
$standing = $waitlists->standing($entry->id);
$standing->status;          // EntryStatus::Waiting
$standing->position;        // 1 for the next in line, null once it is not waiting
$standing->offerExpiresAt;  // the deadline of the offer it holds, or null
```

A participant is an identifier the application gives — an account, an order, a ticket —: letters, digits, `_`, `.`, `:`
and `-`, 190 characters at most. An e-mail address is refused: it changes, and identifies a person. An entry asks for one
place or more, at most `max_places`.

A participant who joins a list they are already in — waiting or holding an offer — gets the same entry back: their
rank and their places do not change. Once out of the list, they join again at the end.

| Refusal | `WaitlistRefused::$reason` |
|---|---|
| The list is closed | `RefusalReason::Closed` |
| More places than `max_places` | `RefusalReason::TooManyPlaces`, with `['max' => …]` in `$parameters` |

The reasons are values for the application to put in its reader's language.

## Releasing places

When places free up in the subject — a registration cancelled, the capacity raised — the application releases them:

```php
$offered = $waitlists->release($waitlist->id, 2); // the entries offered places
```

The list adds them to the places it holds, then asks its policy which waiting entries to offer them to. Each entry picked
holds an offer until the list's deadline: now plus its offer window. The places that fit nobody stay held, and go to the
first entry that fits: one that joins, or one behind when an offer gives places back.

`FirstInLine`, the default, offers places strictly by rank and stops at the first entry that does not fit: a group of
four keeps its turn, and the places wait until it fits. `FirstThatFits` passes over it and offers the places to those
behind who fit; the group keeps its rank for the next places.

When the subject loses places the list holds — the capacity lowered, the event cancelled —, take them back:

```php
$waitlists->reclaim($waitlist->id, 3); // the places taken back, at most those held
```

## Offers

| Call | When | Then |
|---|---|---|
| `accept($entryId)` | Before the deadline | `OfferAccepted`: the application gives the participant the places |
| `decline($entryId)` | While the offer is held | The places go to the next entries |
| `leave($entryId)` | Waiting or holding an offer | The places of an offer go to the next entries |
| `lapseExpired()`, or `phil:waitlist:lapse` | After the deadline | The offer lapses, its places go to the next entries |

`accept()` and `decline()` refuse an entry that holds no offer, with `RefusalReason::NoOffer`; `accept()` refuses an
offer whose deadline has come, with `RefusalReason::OfferExpired`, even before the command has lapsed it. An entry out
of the list stays out: a lapsed offer does not put the entry back in line.

Accepting is where the waiting list ends and the application begins: the application listens to `OfferAccepted`, or
acts after `accept()` returns, and gives the places — a registration confirmed, a ticket issued, an order allowed.

## Draws

A list in draw mode offers nothing until its draw. The entries join as in a queue; the places released before the draw
are held. Then:

```php
$waitlists->draw($waitlist->id, $seed); // the seed to publish; a random one when null
$waitlist->drawSeed();                  // published with the list
```

Each entry waiting at the draw is ranked by the SHA-256 of the seed, a line feed and its identifier, in hexadecimal,
lowest first: anyone with the seed and the identifiers orders them again. A list is drawn once. The places held are then
offered in the order of the draw, and those who join afterwards wait behind the drawn entries, in the order they join. A
seed is 8 to 64 letters, digits, `_`, `.`, `:` and `-`; publish it after the entries are closed, or commit to it
beforehand — announce its hash, reveal it at the draw — so that nobody can choose it to favour an entry.

## Closing

```php
$waitlists->close($waitlist->id);  // no more entries; those in the list keep their turn and their offers
$waitlists->reopen($waitlist->id);
```

## Reading a list

```php
$waitlists->count($waitlist->id);                       // ListCount: places waiting, offered, held
$waitlists->entries($waitlist->id, EntryStatus::Waiting); // by rank, at most `batch`
$waitlists->entry($entryId);
```

## The lock

Each request that changes a list runs under the list's lock: a transaction that first updates the list's row, then
reads the list again. A second request on the same list waits until the first commits, then reads what it saved; the
entries read inside the lock are read again from the database. Two requests never offer the same place, and a refusal
rolls the whole request back.

The services keep no state between requests: they are safe in a worker, such as FrankenPHP's worker mode.

## Events

| Event | When |
|---|---|
| `EntryJoined` | An entry joined a list |
| `PlaceOffered` | An entry was offered places, held until its deadline |
| `OfferAccepted` | An entry accepted its offer |
| `OfferDeclined` | An entry declined its offer |
| `OfferLapsed` | An offer's deadline passed without an answer |
| `EntryLeft` | An entry left its list |
| `WaitlistDrawn` | A list's draw was made |

Each carries identifiers only — the entry's and the list's, or the list's —, and goes out once the change is committed.
A listener reads the rest through `Waitlists::entry()` and `Waitlists::waitlist()`. Routed to Messenger, they stay
small, and carry nothing personal.
