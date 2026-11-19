# Attendance

A participant's attendance is a list of **stays**, `Attendance\Interval`: when they arrived and when they left, or
null while they are still in the room. The stays come from the notifications — `ParticipantJoined` begins one,
`ParticipantLeft` ends it — or from `presences()` asked at intervals: a participant seen for the first time arrived at
that moment, one no longer seen left at that moment. The application keeps them; phil/virtual-classroom-bundle does.

`AttendanceSheet` counts them against a session, from its start to its end:

```php
use Phil\VirtualClassroom\Attendance\AttendanceSheet;

$seconds = AttendanceSheet::seconds($stays, $start, $end);
$percent = AttendanceSheet::percent($stays, $start, $end);
```

- A stay is cut to the session; a stay still open ends with it.
- Overlapping stays count once: a participant on two devices, or the same stay reported by two sources.
- The share is the seconds attended over the seconds of the session, in whole percents rounded down, so that 74.9 %
  never passes a threshold of 75. A session of no length is attended at 0 %.

The moments of the events are in UTC. A moment of a notification is read in seconds or in milliseconds — from 10^11
on, milliseconds —, as a number or digits, or as an RFC 3339 date; a moment before 2000 or after 2200 is no moment.
