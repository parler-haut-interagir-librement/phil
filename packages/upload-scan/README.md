# phil/upload-scan

**Every uploaded file through an antivirus before anyone else can open it.**

phil/upload-scan scans the files an application receives — manuscripts, images, attachments — behind one port,
`Scanner`: a file in, a `ScanResult` out. The result is clean; infected, with the name of the signature that matched;
or an error, with the reason the file could not be scanned to its end. A `ScanPolicy` turns it into a decision: a
clean file is accepted, an infected one rejected, and a file that could not be scanned is rejected or accepted and
flagged, as the application chooses.

It ships `ClamdScanner`, which speaks the protocol of clamd, the daemon of ClamAV, itself: the file is streamed over a
Unix or TCP socket in chunks (`zINSTREAM`), so clamd can run in another container and never reads the application's
disk. The scanner bounds what it sends with a size limit and every read and write with a timeout, and answers an
error result instead of throwing when clamd is down. `NullScanner` and `FakeScanner`, which finds the EICAR test
string, stand in for clamd in tests.

It is a plain PHP library, written from scratch, with no dependency: no third-party ClamAV client, no extension
beyond those PHP builds by default. A scanner keeps nothing between two files, so one instance serves every request of
a long-running worker.

## Status

| Part | State |
|---|---|
| The `Scanner` port, `ScanResult`, `ScanPolicy` and its two answers to an error | Implemented and tested |
| `ClamdScanner`: `INSTREAM` in chunks over a Unix or TCP socket, size limit, timeouts, `PING`, `VERSION` | Implemented and tested |
| The reader of clamd's answers, fuzzed | Implemented and tested |
| `NullScanner` and `FakeScanner` (EICAR and needles of the test's choice) | Implemented and tested |
| Sessions (`IDSESSION`) and several files on one connection | Not started: one connection per file keeps the scanner stateless |
| A Symfony bundle: configuration, a `#[CleanFile]` constraint, quarantine, events, rescans, integrations | Implemented in `phil/upload-scan-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building a scanner. |
| [Scanning](doc/scanning.md) | The port, the results, the policy and its decisions. |
| [clamd](doc/clamd.md) | `ClamdScanner`, the protocol it speaks, the size limit, the timeouts, the probe. |
| [Extension points](doc/extension-points.md) | Another antivirus behind `Scanner`, another way to reach clamd. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmark; `NullScanner` and `FakeScanner` in the application's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/upload-scan
```

```php
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;

$scanner = ClamdScanner::at('tcp://clamav:3310');
$policy = new ScanPolicy(OnScanError::Reject);

$result = $scanner->scanPath($uploadedFile);

if (!$policy->decide($result)->keeps()) {
    // refused: $result->signature names the threat, or $result->reason why it was not scanned
}
```

## Requirements

PHP 8.5 or later; clamd, for `ClamdScanner`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
