# Closing and the date chosen

## By the organizer

```php
$closed = $polls->close($poll->id, $organizer, $slotId);   // true when this call closed it
```

The organizer chooses any date of the poll, before or after the deadline; a date of another poll throws
`InvalidSchedulingPoll`. A poll already closed, by hand or at its deadline, is not closed again: `close()` returns false.

## At the deadline

The schedule `phil_scheduling_poll` sends `CloseDuePolls` at the frequency of `closing.frequency`; its handler calls
`closeDue()`, which closes the polls past their deadline, the most overdue first, `closing.batch` at a time, each on its
best date:

1. the most yes answers;
2. then the most if-need-be answers;
3. then the earliest start.

Dates with the same yes and if-need-be answers share their place in the count; the earliest start orders them and picks
the date. When nobody can come to any date — no yes, no if need be — the poll closes without a date and nothing is sent.

The count runs on the voting engine of `phil/poll`: the dates are the options of a multiple ballot that accepts a blank
one, each respondent casts the ballot of their yes answers and the ballot of their if-need-be answers, and the count of
choices counts each. `SchedulingPolls::tally()` gives the count, a `SlotTally` per date, the best first.

A poll past its deadline that the schedule has not closed yet takes no answer and no respondent; its page says its date
is about to be chosen.

## One closing, one announcement

The closing is one conditional `UPDATE`, made only while the poll is not closed. Of two closings at the same moment —
the organizer's and the schedule's, or two workers of the schedule — one closes the poll and the other is told it did
not. The closing that wins, and only it:

- queues `AnnounceChosenDate`, which carries the poll's identifier only;
- dispatches the event `DateChosen` — poll, slot, who closed it —, or `ClosedWithoutDate`.

The schedule is stateful, in the cache pool of `schedule.cache`, so that a restarted worker does not run the missed
messages twice, and holds a lock of `schedule.lock_factory`; the handlers are idempotent all the same.

## The mail and its iCalendar file

The handler of `AnnounceChosenDate` queues one `SendChosenDate` — the poll's and the respondent's identifiers, nothing
else — per respondent who answered and was not revoked. The handler of `SendChosenDate`, in the worker:

1. reads the address: the one the respondent gave, else the one `RespondentAddresses` finds for their account;
2. claims the notice in one conditional `UPDATE`, so that two workers, or a message handled twice, send it once;
3. builds the mail and sends it.

A respondent without an address, revoked, or who never answered gets nothing. A mail that fails gives the notice back
before the exception goes on, so that Messenger tries the message again. No address, name or token travels through the
queue.

The mail is in the respondent's language, the date written by ICU in their time zone, and carries `event.ics`: one
event in a calendar of RFC 5545, written by the writer of `phil/calendar-sync`, its start and end in UTC, the poll's
title, description and place, and the identifier `phil-scheduling-poll-<poll>`, the same for every respondent, so that a
calendar importing it twice keeps one event. Its words come from the translation domain `phil_scheduling_poll`.
