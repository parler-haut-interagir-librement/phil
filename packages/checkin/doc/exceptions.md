# Exceptions

Every exception of the library implements `Phil\Checkin\Exception\CheckinException`. None of their messages names a
code.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidCheckin` | `InvalidArgumentException` | An identifier, a code, a credential, an attendance, a check-in, a policy or a bound is given a value it cannot work with. |
| `InvalidOfflineBatch` | `InvalidArgumentException` | A journal sent by a device is not a list, holds too many scans, or one of them is malformed; the message gives its position. |
| `LedgerContention` | `RuntimeException` | Every attempt to record a scan met another scan of the credential recorded first; the scan can be sent again. |
| `ManifestTooLarge` | `RuntimeException` | The catalog holds more credentials for a checkpoint than a manifest may carry. |
| `WeakManifestCodes` | `RuntimeException` | The catalog holds a code for a checkpoint under the strength a manifest asks, 64 bits by default. |

An application answers `InvalidCheckin` and `InvalidOfflineBatch` from a device with a client error, and
`LedgerContention` with a server error the device retries.
