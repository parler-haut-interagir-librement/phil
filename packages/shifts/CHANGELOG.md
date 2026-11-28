# Changelog

All notable changes to `phil/shifts` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Shift`, `ShiftKind` and `AssignmentMode`: a fixed shift, a task or a duration within a window of 5 minutes to 31
  days, counting 5 to 1,440 minutes; 1 to 1,000 places and a reserve of 0 to 100; given by sign-up, by a coordinator
  or by allocation among the interested; a required role, a critical flag, a weight of 10 to 1,000 percent and a swap
  approval; instants kept in UTC.
- `Identifier`: the rules of the identifiers of shifts and offers, of volunteers, of roles and of the texts.
- `Roster`, `Place` and `PlaceStatus`: the volunteers of a shift, immutable — sign-up into a place or the reserve,
  interest, assignment by a coordinator in every mode, withdrawal that promotes the first of the reserve, transfer of a
  place —, each volunteer once, within the places, the reserve and 1,000 interested, closed at the start of a fixed
  shift and at the end of the window of a task or a duration.
- `InterestAllocator` and `FairAllocator`: the free places allocated among the interested, those who hold the fewest
  minutes first, then the earliest, then by identifier.
- `Conflicts`: the fixed shifts a volunteer holds that a new one overlaps, with a rest of 0 to 1,440 minutes between
  them.
- `ServedHours`: the minutes served and weighted on the shifts that are over, and the minutes each volunteer holds.
- `SwapOffer` and `SwapStatus`: the swap board — a place offered with a note, taken at once or after a coordinator's
  approval, refused back onto the board, cancelled by its volunteer, stale once its volunteer no longer holds the place.
- `ShiftEvent` and its events — `VolunteerConfirmed`, `VolunteerReserved`, `InterestExpressed`, `VolunteerWithdrew`,
  `ReservePromoted`, `PlaceTransferred`, `SwapOffered` and `SwapTaken` —, carrying identifiers only.
- `ShiftRefused`, `InvalidShift`, `InvalidDocument` and `ShiftsException`: the refusals with a reason a screen
  translates, and the values out of bounds, named by field or path, never by value.
- `ShiftPlan`, `ShiftFormat`, `RosterFormat` and `SwapFormat`: the JSON format `phil-shifts/1` of the shifts of an
  event, read within 1 MiB, a depth of 16 and 2,000 shifts, and the stored rosters and offers, written back the same.
- A fuzz target on the plan reader, and a benchmark of a roster at its bounds.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
