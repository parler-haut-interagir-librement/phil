# Reference

## `Phil\Checkin`

| Type | Role |
|---|---|
| `CheckpointId` | Where a credential is scanned. |
| `DeviceId` | The device a scan was made on. |
| `ScanId` | The identifier of one scan, drawn by its device; `generate()`. |
| `CredentialCode` | What a device read; `fingerprint()`, `estimatedBits()`. |
| `Credential` | What a code stands for: identifier, label, checkpoints, opening, closing, entries, status; `opens()`. |
| `CredentialStatus` | `Active` or `Revoked`. |
| `CatalogEntry` | A credential with one of its codes. |
| `Direction` | `In` or `Out`. |
| `ScanOutcome` | What a scan decided; `changesAttendance()`. |
| `AdmissionPolicy` | The early window and anti-passback; `decide()`. |
| `Attendance` | Entries, inside, last entry and version; `none()`, `restore()`, `after()`. |
| `CheckIn` | One scan of a known credential with its decision; `decided()`, `restore()`. |
| `CheckInDesk` | The online check-in; `scan()`. |
| `ScanResult` | What the desk answers; `collided()` for a scan identifier recorded for another scan. |
| `CredentialResolvers` | Several resolvers asked in their order. |
| `InMemoryCheckInLedger` | A ledger for tests; `all()`. |

## `Phil\Checkin\Contract`

| Type | Role |
|---|---|
| `CredentialResolver` | Finds the credential of a code. |
| `CheckInLedger` | Keeps the check-ins and the attendances. |
| `CredentialCatalog` | Lists the credentials of a checkpoint, finds one by identifier. |

## `Phil\Checkin\Offline`

| Type | Role |
|---|---|
| `ManifestIssuer` | Issues the manifest of a checkpoint for a device; `issue()`. |
| `OfflineManifest` | What a device downloads; `toArray()`. |
| `ManifestEntry` | One credential of a manifest; `toArray()`. |
| `OfflineScan` | One scan decided offline; `of()`, `fromArray()`. |
| `OfflineBatch` | Reads a journal; `read()`. |
| `OfflineSync` | Records a journal; `apply()`. |
| `SyncStatus` | `Recorded`, `Conflict`, `Duplicate`, `Collision` or `Rejected`. |
| `SyncReport` | The status of each scan; `count()`, `toArray()`. |

## `Phil\Checkin\Exception`

| Type | Role |
|---|---|
| `CheckinException` | Every exception of the library. |
| `InvalidCheckin` | A value the library cannot work with. |
| `InvalidOfflineBatch` | A malformed journal. |
| `LedgerContention` | A scan that kept meeting others. |
| `ManifestTooLarge` | A catalog over the manifest's limit. |
| `WeakManifestCodes` | A code too weak for a manifest. |
