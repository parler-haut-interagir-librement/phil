# Changelog

All notable changes to `phil/access-links` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `LinkToken`: tokens of 32 bytes from a `Random\Randomizer` in URL-safe Base64 without padding, and their strict
  reader.
- `TokenHasher`: the HMAC-SHA-256 of a token, `$hmac-sha256$v=1$` and 64 hexadecimal digits, under an ordered list of
  up to eight keys of 32 bytes or more: the first hashes, every key finds; `candidates()` and `matches()` with
  `hash_equals()`.
- `Invitation`: a recipient named by a stable identifier, never holding `@`, invited to a campaign, with its opening,
  closure and expiry, its answer of up to 4096 bytes, its first and last answer, its reminders and its revocation;
  `opensAt()` and `isEditableAt()`.
- `AccessLinks`: `invite()`, one invitation per recipient and campaign; `reissue()`, up to sixteen links per
  invitation; `open()` without side effect, giving an `InvitationAccess`; `respond()` until the closure; `revoke()`,
  `invitation()` and `counts()`, giving `CampaignCounts`; `dueReminders()` and `remind()`, which claims the reminder
  before issuing a fresh link to the application's `ReminderSender`.
- `IssuedLink`: the invitation, the token and the address, never serialized nor shown by `var_dump()`.
- `LinkFormat`: the invitation page's address with the token in a query parameter, on `phil/url`.
- `ReminderSchedule`: up to ten ISO 8601 delays after the invitation, none at the closure or after.
- `Contract\InvitationStore` and `Contract\ReminderSender`; `InMemoryInvitationStore`; `Dbal\DbalInvitationStore` on
  PostgreSQL, MySQL, MariaDB and SQLite: its schema, a recipient unique per campaign, links counted in one conditional
  `UPDATE`, answers and reminder claims in one conditional `UPDATE` each, tested on MySQL 8.4 and PostgreSQL 17.
- `Messenger\ReminderDispatcher`, `Messenger\SendInvitationReminder`, a message that carries the invitation's
  identifier only, and `Messenger\SendInvitationReminderHandler`.
- Exceptions under `AccessLinksException`: `InvalidConfiguration`, `InvalidToken`, `LinkNotUsable`,
  `InvitationClosed`, `AlreadyInvited`, `UnknownInvitation`.
- The fuzz target `link-token`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
