# Changelog

All notable changes to `phil/listings` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `ListingId`, `MemberId` and `ResponseId`: identifiers the application chooses, 1 to 64 ASCII letters, digits, `.`,
  `_`, `:` or `-`, without personal data.
- `ListingKind` and `ModerationReason`: lowercase ASCII keys of at most 64 bytes.
- `Title`: valid UTF-8, white space folded, no control or format character, 1 to 120 characters, input bounded to
  2048 bytes before any pattern runs.
- `Location`: online, or an area shown to everyone with an exact place shown only to the author and to accepted
  respondents; `approximate()`.
- `Criterion` — a choice of up to 32 values, an integer range or a flag — and `Criteria`, at most 32 per listing, one
  per name; `meets()` tells whether a listing's criteria meet the criteria a search wants.
- `KindPolicy`: lifetime, reminder, extension and number of extensions in days, `Moderation` before or after
  publication, reports that hide a listing, minimum age, whether the kind takes responses and how many open ones,
  every number bounded; `KindPolicies`, the policies by kind, each kind declared once.
- `Listing` and `ListingStatus`: the life of a listing as pure transitions — `draft()`, `submit()`, `approve()`,
  `reject()`, `remove()`, `report()`, `edit()`, `extend()`, `close()`, `at()`, `markReminded()` —, `isVisibleAt()`,
  `needsReminderAt()`, `admitsAge()`, `locationFor()` and `restore()`; the minimum age is the older of the listing's
  and the kind's.
- `Response` and `ResponseStatus`: a response without message, pending, accepted, declined or withdrawn;
  `permitsContact()` only once both members consent.
- `Responder` and `Refusal`: who may respond — a visible listing of a kind that takes responses, never its author,
  no block in either direction, the minimum age, one open response per member, the kind's bound of open responses —
  and the author's acceptance, refused when the listing is no longer visible, after a block, or when the respondent
  no longer meets the minimum age.
- `BlockList`, `MemberAges` and `ResponseStore`, the ports the application implements.
- `ListingsException`, implemented by `InvalidListing`, `ForbiddenTransition`, `UnknownKind` and `ResponseRefused`.
- The benchmark of a search's meeting of criteria, the visibility of a listing, a title and a set of criteria.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
