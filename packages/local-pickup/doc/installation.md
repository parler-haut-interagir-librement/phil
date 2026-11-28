# Installation

```sh
composer require phil/local-pickup
```

The package needs PHP 8.5 or later. It requires phil/reservation, for the slots, and phil/qr-code, for the QR code of a
pickup code; neither needs a PHP extension beyond what they declare.

## What the application provides

| What | Why |
|---|---|
| A store for the pickups, implementing `Contract\PickupRepository` | Keeps what `Pickup::toArray()` gives: a table with a JSON column, or columns of its own. |
| A journal, implementing `Contract\HandOverLog` | Keeps every hand-over, and who collected. |
| A secret of at least 32 bytes | Keys the digest of the codes: `PickupCodes` refuses a shorter one. Keep it in the application's secrets. |
| A bus, implementing `Contract\ReadyNoticeDispatcher` | Sends `Message\NotifyPickupReady` to a worker, which calls `Message\NotifyPickupReadyHandler`. |
| A notifier, implementing `Contract\ReadyNotifier` | Sends the ready notice with its code; `Contract\ArrivalNotifier` tells the staff of an arrival. |
| One expiry port | `Contract\ExpiredPickupCanceller` or `Contract\ExpiredPickupRestocker`, as `ExpiryOutcome` chooses. |
| A schedule | Calls `Expiry::run()` every few minutes. |
| A lock per pickup point | Puts two customers taking the last place of a slot one after the other around `Pickups::place()`. |

`Memory\InMemoryPickups` and `Memory\InMemoryHandOverLog` serve tests and a single process.
