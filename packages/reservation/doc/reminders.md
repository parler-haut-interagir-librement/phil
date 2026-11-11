# Reminders

```php
$schedule = new ReminderSchedule(1440, 60);   // a day before, then an hour before
$schedule->moments($booking->interval);        // the two moments, the earliest first
$schedule->due($booking->interval, $now, $lastSent);
```

The offsets are minutes before the start, from 1 to a year, at most 20; they are kept once each, the furthest first.
An offset out of bounds, or more than 20, throws `InvalidDefinition`.

`due()` gives the offset of the reminder to send now, or `null`: the one closest to the start whose moment has come,
after the last one sent, as long as the booking has not started. A reminder whose moment passed unsent is overtaken:
a booking made an hour before its start gets the hour's reminder only, and a job that ran late sends one reminder,
not several. The application keeps the offset it sent last and passes it back; the library keeps nothing.
