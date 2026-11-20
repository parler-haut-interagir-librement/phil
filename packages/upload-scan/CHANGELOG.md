# Changelog

All notable changes to `phil/upload-scan` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha: a scan that could not read the whole file, or the whole answer of clamd, is an error
result, never a clean file.

### Added

- The documentation of clamd on a local Unix socket: `LocalSocket`, its group and mode, the user PHP runs as, systemd
  and containers.
- The documentation of archives and clamd's limits — `MaxScanSize`, `MaxFileSize`, `MaxFiles`, `MaxRecursion` and
  `AlertExceedsMax`, without which an archive past a limit comes back clean — and of the versions to scan with, ClamAV
  1.5.4 or later, or 1.4.6 on the 1.4 line.
- The [ASVS 5.0 mapping](doc/asvs.md).
- Integration tests against a real clamd: the test file three archives down, and an archive past `MaxFiles`, with and
  without `AlertExceedsMax`.

### Security

- An answer of clamd without its null byte, such as `stream: OK` followed by a closed connection, was read as clean:
  it is an error result, `clamd closed the connection before the end of its answer.`; an answer of 4 096 bytes without
  one is an error too. `ping()` answers `false` and `version()` `null` for an answer cut short.
- A source stream that timed out, or gave nothing more without having ended, was taken for the end of the file, and the
  start of the file was scanned and found clean: `ClamdScanner` and `FakeScanner` answer an error result and send no
  last chunk.

## 0.1.0-alpha - 2026-11-11

### Added

- `Scanner`, the port of an antivirus: `scanStream()`, the whole stream, a seekable one rewound first and one that
  cannot be rewound refused once read, and `scanPath()`, an error result rather than an exception for a file that
  could not be scanned; `ScannerProbe`, `ping()` and `version()` for a health check.
- `ScanResult` and `ScanStatus`: clean, infected with its signature, or an error with its reason, each text 1 to 255
  bytes of printable ASCII.
- `ScanPolicy`, `OnScanError` and `Decision`: a clean file accepted, an infected one rejected, a file that could not be
  scanned rejected or accepted and flagged.
- `ClamdScanner`: the clamd protocol spoken by the library, `zINSTREAM` in chunks of 64 KiB by default, a size limit of
  25 MiB by default, a timeout on every read and write, one connection per command, `PING` and `VERSION`;
  `ClamdScanner::at()` for a `unix://` or `tcp://` address.
- `ClamdConnector`, the port of the connection to clamd, and `SocketConnector`, a Unix or TCP socket with a connect
  timeout.
- `ClamdResponse`, the reader of clamd's answers: a result whatever the bytes, `FOUND` always an infection; its fuzz
  target `clamd-response`.
- `NullScanner`, which finds every file clean, and `FakeScanner`, which finds the EICAR test string and the needles
  given, answers a given error, or is unreachable.
- `UploadScanException`, implemented by `InvalidArgument` and `ScannerUnavailable`.
- The benchmark of a 1 MiB file framed for clamd, the fake scanner over it, and an answer read.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
