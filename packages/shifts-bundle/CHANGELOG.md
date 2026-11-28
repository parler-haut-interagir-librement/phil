# Changelog

All notable changes to `phil/shifts-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `PhilShiftsBundle` and its configuration `phil_shifts`: the connection, the person signed in, the allocator, the
  pages, the time zone of the pages and of the form, the rest between two fixed shifts, the roles of the volunteers and
  of the coordinators, the source of birth dates with the age of majority, and the threshold and window of the
  absences; a compiler pass that refuses the pages without CSRF protection, Security or Forms.
- `ShiftsTables`, `ShiftsSchemaListener`, `DbalRosterRepository`, `DbalSwapRepository`, `DbalGuardianRepository`,
  `DbalAbsenceRepository` and `DbalTransaction`: the shifts with their rosters, the rows of their places, the offers of
  the swap board, the contacts of the guardians and the absences through Doctrine DBAL, their instants in UTC, each write over the revision read, a completed swap written with its roster in one transaction.
- `Rosters`: shifts planned, corrected and removed; sign-up, interest, assignment, withdrawal and allocation, a fixed
  shift that overlaps one the volunteer holds refused, a minor without the consent of a guardian and a volunteer past
  the threshold of absences refused, a write done again from a fresh read up to three times, and the events dispatched
  once stored.
- `Minors`, `BirthDates` and `GuardianContact`: a volunteer who is a minor on the day of a shift, by the birth date the
  application gives, joins it once the contact of a guardian — a name, a telephone number or an email address — is
  recorded with their consent.
- `Absences` and `Absence`: unannounced absences recorded by a coordinator once a shift started, counted over a window,
  a threshold past which a volunteer no longer joins a shift by themselves, and the lift of the block by an organiser.
- `SwapBoard`: offers made, taken, approved, refused and cancelled, the board of the offers still valid, and a place that
  would overlap a shift of the volunteer who takes it, or that a minor without consent or a blocked volunteer would
  take, refused.
- The volunteers' pages — the shifts to come, a shift, one's own shifts and hours, the swap board — and the
  coordinators' pages — every shift, the form of a shift, a roster with its absences and blocks, the swaps to
  approve —, plain Twig forms with CSRF
  tokens that work without JavaScript, the reason of a refusal announced to screen readers.
- `ShiftType`: the form of a shift in the configured time zone, the library's refusal added to the form.
- The components `PhilShifts:ShiftCard` and `PhilShifts:MyShifts`, with Symfony UX Twig Component.
- `AllocateInterest` and its handler: the allocation through Messenger, carrying the identifier of the shift only.
- `ShiftsVoter`, `TokenCurrentPerson` and `CurrentPerson`: who volunteers, who coordinates, and the roles a shift
  requires read from the token.
- `phil:shifts:import`: a plan in the format `phil-shifts/1` imported, the shifts that exist kept or corrected.
- `ShiftsPrivacy`: a volunteer's shifts, offers, guardian's contact and absences in the export and the erasure of
  phil/privacy-bundle.
- The translations in English and French, the Flex recipe in `recipe/0.1`, and the documentation in `doc/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
