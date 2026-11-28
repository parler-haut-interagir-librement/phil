# Changelog

All notable changes to `phil/workshops-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Workshop`, `WorkshopSession` and `Registration`, mapped in Doctrine on their own, their moments in UTC and given
  back on the session's clock; every value bounded, a participant an identifier and never an e-mail address.
- `Workshops`: creates workshops; schedules, moves, resizes and cancels sessions; registers and cancels under the
  session's lock; counts seats with `SeatCount`. Registering twice gives back the same registration; a registration
  takes only the free seats nobody claims, and the capacity never goes below the seats taken and offered.
- The `SeatClaims` port: the free seats a waiting list claims, offered or asked for by those waiting.
- The optional integration of `phil/waitlist-bundle`, in `Integration\Waitlist`: one waiting list per session,
  `SessionWaitlists` to join it and to accept its offers into confirmed registrations, `WaitlistSeatClaims`, and the
  listener that balances the list when seats free up or an entry leaves and closes it when the session is cancelled.
- The refusals, `RefusalReason`, and the statuses in English and French through `RefusalMessages`.
- The events `RegistrationConfirmed`, `RegistrationCancelled`, `SessionResized`, `SessionRescheduled` and
  `SessionCancelled`, with identifiers only.
- The options `max_seats` and `waitlist`, and the Flex recipe.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
