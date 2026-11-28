# Changelog

All notable changes to `phil/waitlist-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Waitlist` and `Entry`, mapped in Doctrine by attributes on their own, their moments in UTC; one list per subject, a
  kind and an identifier; every value bounded, a participant an identifier and never an e-mail address.
- `Waitlists`: opens a subject's list; joins under the list's lock, joining twice giving back the same entry; gives an
  entry's position with `Standing` and a list's places with `ListCount`; lists the entries by rank.
- Places released by the application and offered to the next entries, each holding its offer until a deadline from
  the list's offer window; places that fit nobody held until someone fits, or reclaimed.
- Offers accepted before their deadline, declined, given back by an entry that leaves, or lapsed by
  `Waitlists::lapseExpired()` and the `phil:waitlist:lapse` command, list by list; the places given back go to the
  next entries at once.
- The `OfferPolicy` port, `FirstInLine` by default and `FirstThatFits`; the policy's pick is checked.
- The draw mode: nothing offered before the draw, then the entries ranked by the SHA-256 of a published seed and their
  identifier, those who join afterwards behind them.
- The refusals, `WaitlistRefused` with its `RefusalReason`, and `NotFound`.
- The events `EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferDeclined`, `OfferLapsed`, `EntryLeft` and
  `WaitlistDrawn`, with identifiers only.
- The notifications of the participants through phil/notifications-bundle, an optional integration: a type per event,
  sent only when declared to that bundle.
- The options `offer_window`, `max_places`, `policy`, `batch` and `notifications`, and the Flex recipe.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
