# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\UploadScan\Contract\Scanner` | interface | An antivirus: `scanStream()`, `scanPath()` |
| `Phil\UploadScan\Contract\ScannerProbe` | interface | Whether the engine answers: `ping()`, `version()` |
| `Phil\UploadScan\Contract\ClamdConnector` | interface | A connection to clamd: `connect()` |
| `Phil\UploadScan\ScanResult` | `final readonly class` | Clean, infected or an error: `clean()`, `infected()`, `error()`, `isClean()`, `isInfected()`, `isError()`, `MAX_TEXT_BYTES` |
| `Phil\UploadScan\ScanStatus` | enum | `clean`, `infected`, `error` |
| `Phil\UploadScan\ScanPolicy` | `final readonly class` | A result to a decision: `decide()` |
| `Phil\UploadScan\OnScanError` | enum | `reject`, `accept_and_flag` |
| `Phil\UploadScan\Decision` | enum | `accept`, `flag`, `reject`; `keeps()` |
| `Phil\UploadScan\Clamd\ClamdScanner` | `final readonly class` | clamd over its protocol: `at()`, `scanStream()`, `scanPath()`, `ping()`, `version()`, `DEFAULT_MAX_BYTES`, `DEFAULT_CHUNK_BYTES` |
| `Phil\UploadScan\Clamd\SocketConnector` | `final readonly class` | A Unix or TCP socket to clamd |
| `Phil\UploadScan\Clamd\ClamdResponse` | `final class` | The reader of clamd's answers: `scan()`, `isPong()`, `version()`, `MAX_BYTES` |
| `Phil\UploadScan\NullScanner` | `final readonly class` | Every file clean |
| `Phil\UploadScan\FakeScanner` | `final readonly class` | The EICAR string and given needles infected: `eicar()`, `EICAR_SIGNATURE` |
| `Phil\UploadScan\Exception\UploadScanException` | interface | Every exception of the library |
| `Phil\UploadScan\Exception\InvalidArgument` | `final class` | A value the library refuses |
| `Phil\UploadScan\Exception\ScannerUnavailable` | `final class` | clamd cannot be reached, thrown by a connector |
