# Offline

A door does not close when the network goes down. A device that expects to lose it downloads an offline manifest
beforehand, decides scans from it while offline, and sends its journal once back online.

## Manifests

`ManifestIssuer::issue($checkpoint, $device)` lists the credentials of the catalog that open the checkpoint, reads
their attendance from the ledger in chunks of 500, and returns an `OfflineManifest`:

| Part | Meaning |
|---|---|
| `checkpoint`, `device` | Who it was issued for. |
| `issuedAt`, `expiresAt` | When; valid 12 hours by default, 60 seconds to a week. |
| `key` | 32 random bytes drawn for this manifest alone. |
| `policy` | The early window and anti-passback, for the device to decide the same way. |
| `entries` | One `ManifestEntry` per code: its fingerprint, the credential and its attendance. |

`toArray()` is what the device reads:

```json
{
    "format": 1,
    "checkpoint": "hall",
    "device": "door-1",
    "issuedAt": 1794132000,
    "expiresAt": 1794175200,
    "key": "q0Vh3…",
    "policy": {"earlySeconds": 1800, "antiPassback": true},
    "credentials": [
        ["5d41…", "reg-4411", null, 1794128400, 1794387600, null, 1, false, false]
    ]
}
```

Each credential is a list: fingerprint, identifier, label or null, opening and closing as Unix timestamps or null,
maximum entries or null, entries used, inside, revoked. The key is in URL-safe base64 without padding.

### What a lost device gives away

The codes never leave the server. A fingerprint is the HMAC-SHA256 of a code under the manifest's key: it lets the
device recognise a code it reads. The key travels with the fingerprints, so whoever holds a lost device can compute
the fingerprint of every code they can guess and look it up: a manifest keeps the codes only as well as they are long
and random. Eight-digit ticket numbers are 10⁸ guesses, a few minutes on a laptop, and every valid code of the
checkpoint is out until it is revoked. Each manifest draws its own key, so two devices never hold the same
fingerprints, but that does not slow down the guessing.

The issuer therefore refuses, with `WeakManifestCodes`, a catalog that holds a code under 64 bits for the checkpoint.
`CredentialCode::estimatedBits()` gives the strength of a code: its length times the bits a character carries in the
narrowest alphabet that holds it — digits, hexadecimal digits of one case, digits and letters of one case, URL-safe
base64, or any printable character. It is an upper bound: a code that follows a pattern, a date or a sequence, carries
fewer bits than its estimate, so the codes of an offline checkpoint are drawn at random. 16 random hexadecimal digits,
11 characters of URL-safe base64 or 20 random digits reach 64 bits; a QR code carries far more with no effort for the
holder. `minCodeBits` chooses another minimum, from 0, which takes any code, to 128.

Labels — often the holders' names — are left out unless the issuer is built with `labels: true`. The limit (10 000 by
default, at most 100 000) bounds what a device stores; a catalog over it throws `ManifestTooLarge`.

## The journal

While offline, the device decides each scan from its manifest with the same policy, updates its own copy of the
attendance, and keeps an `OfflineScan` in its journal: the scan's identifier, the credential, the direction, the
outcome it decided and the instant by its clock. A code that matches no fingerprint is unknown and not kept.

`OfflineBatch::read()` reads the journal as the device sends it, a JSON list of at most 500 scans:

```json
[{"id": "0f8fad5b-d9cb-469f-a165-70867728950e", "credential": "reg-4411", "direction": "in", "outcome": "admitted", "scannedAt": 1794132061}]
```

A malformed scan refuses the whole batch with `InvalidOfflineBatch`, whose message gives its position and never the
values sent; nothing is recorded.

## The synchronisation

`OfflineSync::apply($checkpoint, $device, $scans)` applies the scans by instant, then by identifier, and returns a
`SyncReport` with a `SyncStatus` per scan:

| Status | When |
|---|---|
| `Recorded` | Recorded, the ledger deciding as the device did. |
| `Conflict` | Recorded with the device's decision, the ledger deciding otherwise. |
| `Duplicate` | Already recorded under its identifier: the same scan, made offline by the same device at the same checkpoint, of the same credential, in the same direction, with the same outcome. |
| `Collision` | Not recorded: its identifier is recorded for another scan. |
| `Rejected` | Its credential is no longer in the catalog, or its instant is out of bounds. |

Each scan is decided again by the policy on the ledger's attendance at its instant. It is recorded with the device's
decision, offline, because the holder already met it at the door: an entry two offline devices both admitted counts
twice, and the second carries `AlreadyAdmitted` as its conflict, for the staff to look into. A refusal the ledger
would have admitted is recorded with `Admitted` as its conflict and changes nothing.

The ledger's attendance follows the device's claim. `apply()` checks neither that the credential was in a manifest
issued to that device for that checkpoint nor that the manifest was still valid: a scan of any credential the catalog
knows is recorded with the outcome the device sends, and counts toward anti-passback, the maximum entries and the
attendance figures, a conflict only marking it for review. Whoever can post a journal is trusted as much as a door
scanner: the application authenticates each device, revokes the token of a device lost or tampered with, and has the
staff review the conflicts — a conflict it may undo by recording the ledger's decision.

An instant older than the maximum age (two days by default) or later than now plus the clock skew (five minutes by
default) is rejected; one in the future within the skew is recorded as now. Scans made offline are ordered among
themselves; one made before an online scan already recorded is still counted after it.

The device forgets every scan the report names, whatever its status, and sends the others again later.
