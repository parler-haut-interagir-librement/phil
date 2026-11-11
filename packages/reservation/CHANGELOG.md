# Changelog

All notable changes to `phil/reservation` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Interval`, a span of time read to the second, its end excluded; `Booking`, `BookingRequest`, `Violation`,
  `ViolationCode` and `Decision`; `ReservationException`, `InvalidInterval` and `InvalidDefinition`.
- `Bookable`: capacity, buffers that add up, availability, rules and time zone; `check()` gives every reason a request
  is refused and the bookings it meets, `free()` the units free over a span, `slots()` the free slots of a period by a
  step restarted at each midnight, bounded by `MAX_CANDIDATES` and `MAX_SLOTS`. `LoadProfile` lays out the units held
  once and reads the peak of a span by a binary search.
- The `Rule` port and its rules: `MinimumNotice`, `MaximumAdvance`, `DurationLimits` and `SlotGrid`.
- The `Availability` port, `AlwaysAvailable` and `WeeklyAvailability`, whose ranges that touch make one span across
  midnight, with days closed, read over 1,000 days at most.
- `ReminderSchedule`: the offsets of a booking's reminders, their moments, and the reminder due, a missed one overtaken
  by the next.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
