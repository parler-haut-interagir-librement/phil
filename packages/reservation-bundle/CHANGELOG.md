# Changelog

All notable changes to `phil/reservation-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `BookableResource`, mapped to `phil_reservation_resource`: name, capacity, time zone, buffers that add up between
  neighbours, rules, weekly hours and days closed, reminders, hold time and activity, each value checked by
  `phil/reservation`'s own types; `bookable()` and `reminderSchedule()` give the library's objects. A room, a table or
  a person is one.
- `Reservation`, mapped to `phil_reservation`: span and creation in `datetime_utc_immutable` columns, read on the
  resource's clock, quantity, holder identifier — an e-mail address refused —, `ReservationStatus` (held, confirmed,
  cancelled, expired), hold expiry, last reminder sent and version; `InvalidReservation`.
- The `ResourceRepository` and `ReservationRepository` ports and their Doctrine adapters, sorting by PHP's
  `SortDirection` and comparing moments in UTC; `exclusively()` runs a request in a transaction that first updates the
  resource's row, and leaves the entity manager open on a refusal.
- `Reservations`: `check()`, `hold()`, `book()`, `confirm()` (an expired hold checked again, its notice counted from
  when it was made), `reschedule()`, `cancel()`, `slots()` and `expireHolds()`; `ReservationRefused` with its
  decision and `NotFound`; the events `ReservationHeld`, `ReservationConfirmed`, `ReservationRescheduled`,
  `ReservationCancelled` and `ReservationExpired`, carrying identifiers only, through a PSR-14 dispatcher.
- `Reminders` with the `ReminderSender` port, a reminder recorded before it is sent; `MessengerReminderSender` and
  the `SendReservationReminder` message.
- `ReservationType` over `ReservationDraft`, whose reasons become errors of the form, and `ViolationMessages`, with
  the messages of the `phil_reservation` domain in English and French.
- `phil:reservation:expire-holds` and `phil:reservation:send-reminders`.
- `ReservationItems`, the confirmed reservations in `phil/calendar-bundle`'s views, titled and tagged with their
  resource.
- The configuration `phil_reservation`: `batch` and `calendar`.
- The integration suite on MySQL 8.4 and PostgreSQL 17: mapping, queries, version, cascade, buffers of neighbours, and
  requests in separate processes racing for the last unit.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README, with the lock on each database, an optional
  PostgreSQL exclusion constraint, and the uses built on the bundle.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
