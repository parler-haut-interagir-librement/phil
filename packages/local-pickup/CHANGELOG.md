# Changelog

All notable changes to `phil/local-pickup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `PickupPoint` and `PickupPointKind`: a store's counter or a convention booth, tied to a stock location, with slots
  from a phil/reservation resource whose capacity is the number of orders a slot takes, a slot length, a preparation
  time, a hold period, and whether it takes new orders.
- `AgreedPickupPoint`: the copy of the point an order keeps from the moment it is placed — name, address, stock
  location, time zone and hold period —, so that a point that changes, closes or is no longer allowed changes nothing
  for the orders already placed.
- `PickupSlots`: the slots of a period a customer may choose now, open, following the point's rules, with a place left
  and no sooner than the preparation time; `check()` gives phil/reservation's reasons, and `TooSoon` with the
  preparation time.
- `Pickup` and `PickupState`: a pickup being prepared, ready, picked up or expired, held for the agreed hold period from
  its slot's start or from when it is ready, stored as a JSON-ready array and read back strictly.
- `Pickups`: placing in a slot, making ready, sending a new code, and the customer's arrival, automatic or by their own
  "I am here" (`ArrivalSource`), announced to the staff once.
- `Message\NotifyPickupReady` and `Message\NotifyPickupReadyHandler`: the ready notice asked by a message that carries
  the order number only; the worker reads the pickup again, issues the code, keeps its digest and sends the
  `ReadyNotice`, once even when the message is delivered twice.
- `PickupCodes`, `IssuedCode` and `ScannedCode`: eight random characters of Crockford's base 32, read forgivingly,
  kept as an HMAC-SHA-256 digest keyed by a key derived from the secret with HKDF and bound to the order, shown in
  two groups and as
  a QR code drawn by phil/qr-code, whose payload is read back from a scanner.
- `Counter`, `HandOverRequest` and `HandOver`: the hand-over against the code, compared in constant time, wrong
  attempts counted and the code locked at the limit, a right code spent at once, and every hand-over journalled with
  the collector's name and the designated one when someone collects in the customer's place.
- `Contract\PickupDesk`: the port a counter screen and a point-of-sale register both call to hand an order over.
- `Expiry` and `ExpiryOutcome`: the pickups nobody collected within their hold period ended in bounded runs, and their
  orders cancelled and refunded or their goods put back in stock, as configured.
- `PickupPersonalData`: the export and the erasure of the names of the people designated to collect, and of those who
  collected, for an application's privacy module.
- The ports `Contract\PickupRepository`, `Contract\HandOverLog`, `Contract\ReadyNoticeDispatcher`,
  `Contract\ReadyNotifier`, `Contract\ArrivalNotifier`, `Contract\ExpiredPickupCanceller` and
  `Contract\ExpiredPickupRestocker`, with `Memory\InMemoryPickups` and `Memory\InMemoryHandOverLog` for tests and a
  single process.
- The exceptions `InvalidPickup`, `UnknownPickup`, `PickupRefused`, `CodeRefused` and `CodeLocked`, under
  `LocalPickupException`.
- A fuzz target for the stored rows and the scanned payloads, and benchmarks of the slots of a week, the code check
  and a stored pickup read back.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
