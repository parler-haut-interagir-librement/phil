# Checking in

## Codes

A `CredentialCode` is what a device read: the payload of a scanned code, or a number typed at the desk. It is valid
UTF-8 without control characters, from 1 to 512 bytes, and is taken as it is: a scanner's line feed is removed by
the device. Whoever holds a code gets in, so the library treats it as a secret: no check-in records it, no exception
message names it, and an offline device only receives `fingerprint($key)`, its HMAC-SHA256 under a key of the
manifest. A number typed at the desk suits online check-in only: an offline manifest asks codes of 64 bits by default
(see [Offline](offline.md)).

## Credentials

A `Credential` is what the application says a code stands for:

| Part | Meaning |
|---|---|
| `id` | The application's identifier, 1 to 128 printable ASCII characters: what check-ins name. |
| `label` | What the staff sees — a name, a category of ticket —, or null. |
| `checkpoints` | The `CheckpointId`s it opens; none opens them all. |
| `validFrom`, `validUntil` | When it opens and closes, each optional, to the second. |
| `maxEntries` | How many times it lets its holder in: one by default, null without limit. |
| `status` | `CredentialStatus::Active` or `Revoked`: a refunded ticket, a cancelled registration. |

```php
$pass = Credential::of('reg-4411', 'Élise Tremblay', [CheckpointId::of('hall'), CheckpointId::of('stage')], $friday, $sunday, null);
```

## The admission policy

`AdmissionPolicy` decides a scan on the credential, its attendance, the checkpoint, the direction and the instant, in
this order:

1. a revoked credential is `Revoked`;
2. a checkpoint it does not open is `WrongCheckpoint`;
3. an exit is `CheckedOut` when the holder is inside, `NotInside` otherwise;
4. an entry before the opening, less the early window, is `NotYetValid`;
5. an entry after the closing is `Expired`;
6. an entry of a holder inside under anti-passback, or of a credential whose entries are used, is `AlreadyAdmitted`;
7. any other entry is `Admitted`.

`earlySeconds` (0 to 86 400, none by default) lets people in before the opening, when the doors open early.
`antiPassback` (on by default) refuses a second entry until the holder checks out, so that a code passed back over
the fence admits nobody else; turned off, an unlimited credential comes in as often as it is scanned.

The decision is pure: the same inputs give the same outcome, at the desk and on an offline device, whose script
follows the same order.

## Attendance

`Attendance` is where a credential stands: its `entries`, whether its holder is `inside`, `lastEntryAt`, and a
`version` that counts the admissions and check-outs recorded. An `Admitted` check-in adds an entry and puts the
holder inside; a `CheckedOut` one lets them out; a refusal changes nothing.

## The desk

`CheckInDesk::scan()` resolves the code, reads the attendance, decides and records a `CheckIn`, refusals included:
the staff can see who was turned away and why. An unknown code records nothing.

```php
$result = $desk->scan($code, CheckpointId::of('hall'), DeviceId::of('door-1'), Direction::In, ScanId::of($fromDevice));
```

The `ScanResult` gives the `outcome`, the `credential`, the `attendance` once recorded — `lastEntryAt` says when a
holder refused as already admitted came in —, the `checkIn`, and `replayed`.

### One scan, one record

A device draws a `ScanId` for each scan and sends it with the code. A scan sent again under the same identifier — a
retry after an answer lost on a weak network — is not decided again: the desk answers with the check-in recorded the
first time, `replayed` set. The scan must be the same: its code stands for the credential recorded, at the same
checkpoint, from the same device, in the same direction. Any other scan under an identifier already recorded — an
unknown code, another credential, another door — is answered `Collision`: nothing is recorded, and neither the outcome
nor the check-in recorded under the identifier is given back, so that an identifier guessed or seen gets nobody in and
reveals no one's entry. The device sends the scan again under a new identifier. Without an identifier, the desk draws
one.

### Two doors, one entry

An admission or a check-out carries its sequence, one more than the version of the attendance it was decided on, and
the ledger records it only if that version is still the last. When two devices scan the same single-entry ticket at
once, one records first; the other's record is refused, and the desk decides again on the new attendance — this time
`AlreadyAdmitted`. After three refusals in a row, it throws `LedgerContention`, and the scan can be sent again under
the same identifier.
