# Reference

Every public type of the package, with its role.

## Services

| Type | Role |
|---|---|
| `PickupSlots` | The slots of a period a customer may choose now; the decision on one slot. |
| `Pickups` | Places a pickup, makes it ready, sends a new code, records an arrival. |
| `PickupCodes` | Issues a code with its keyed digest; checks a code given; normalizes what was typed. |
| `Counter` | Hands a ready order over against its code, counts wrong attempts, journals the hand-over. |
| `Expiry` | Ends the overdue pickups in bounded runs and calls the configured port. |
| `PickupPersonalData` | Exports and erases the collectors' names of a customer. |
| `Message\NotifyPickupReady` | The message of a ready notice: the order number only. |
| `Message\NotifyPickupReadyHandler` | Reads the pickup again, issues the code and sends the notice, once. |

## Values

| Type | Role |
|---|---|
| `PickupPoint` | A store's counter or a convention booth: stock location, slots, preparation time, hold period. |
| `PickupPointKind` | `Store` or `ConventionBooth`. |
| `AgreedPickupPoint` | The copy of the point an order keeps from the moment it is placed. |
| `Pickup` | One order's pickup: point, slot, state, hold, code digest, attempts, arrival, collector. |
| `PickupState` | `Preparing`, `Ready`, `PickedUp`, `Expired`; `holdsSlot()`. |
| `ArrivalSource` | `Automatic` or `Manual`. |
| `ExpiryOutcome` | `CancelAndRefund` or `Restock`. |
| `IssuedCode` | A code just issued: its display, its QR payload, its QR code and SVG, its digest. |
| `ScannedCode` | The order number and code read from a scanned payload. |
| `HandOverRequest` | The code given, the station, the staff, the moment and the collector. |
| `HandOver` | A journal entry: the order, the customer, the point, the station, the staff, the moment and the names. |
| `ReadyNotice` | What the ready notice tells: the agreed point, the slot, the end of the hold, the code. |

## Ports

| Type | Role |
|---|---|
| `Contract\PickupRepository` | Keeps the pickups. |
| `Contract\HandOverLog` | Journals the hand-overs. |
| `Contract\ReadyNoticeDispatcher` | Sends the order number of a ready notice to a worker. |
| `Contract\ReadyNotifier` | Sends the ready notice. |
| `Contract\ArrivalNotifier` | Tells the staff of an arrival. |
| `Contract\ExpiredPickupCanceller` | Cancels and refunds an expired order. |
| `Contract\ExpiredPickupRestocker` | Puts an expired order's goods back in stock. |
| `Contract\PickupDesk` | Hands an order over: the port of counters and registers. |
| `Memory\InMemoryPickups` | A pickup store in an array. |
| `Memory\InMemoryHandOverLog` | A journal in an array. |

## Exceptions

| Type | Thrown when |
|---|---|
| `Exception\LocalPickupException` | The interface of every exception below. |
| `Exception\InvalidPickup` | An identifier, a setting, a stored row or a payload is out of shape. |
| `Exception\UnknownPickup` | No pickup is kept for the order. |
| `Exception\PickupRefused` | A pickup cannot be placed, made ready, handed over or expired; `reason` is a stable key, `violations` the slot's reasons. |
| `Exception\CodeRefused` | A wrong code; `attemptsLeft`. |
| `Exception\CodeLocked` | The code took too many wrong attempts. |
