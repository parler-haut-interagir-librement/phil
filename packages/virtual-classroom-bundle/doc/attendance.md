# Attendance and recordings

## Stays

A **stay** is a participant's time in a room: when they arrived, and when they left or null while they are in it. The
store keeps one row per stay, named by the room, the participant and the moment they arrived.

- An arrival while the participant is in the room, or within a stay already known — the same arrival reported by the
  notification and by `sync()`, a second device —, begins none.
- A departure ends the participant's latest open stay that began by then; without one, it ends none.
- When a room ends, every open stay ends with it. A stay reported after the end — the attendance BigBlueButton posts
  once the session is over — is kept, and one still open ends at the end of the room.

`ParticipantArrived` and `ParticipantDeparted` are dispatched for each stay begun or ended; `AttendanceUpdated` when
the room ends, and when the stays of an ended room change.

## The report of a session

```php
foreach ($classrooms->attendance($room->id) as $report) {
    $report->participant;   // ParticipantId
    $report->stays;         // list<Interval>
    $report->seconds;       // within the session, overlaps counted once
    $report->percent;       // whole percents, rounded down
}
```

The session runs from the room's opening — or its first arrival, when the room never opened through the application —
to its end, or now while it runs. The counting is `AttendanceSheet`'s, of phil/virtual-classroom.

## Recordings

A recording is kept once per identifier and format; a recording known again updates its address. `recordings()` gives
those of a room; `RecordingAvailable` is dispatched for each new one. A JaaS recording carries a download link that
works for a day, `expiresAt`: copy the file to the application's storage on `RecordingAvailable` if it must stay.
