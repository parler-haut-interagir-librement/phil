# Changelog

All notable changes to `phil/upload-scan` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
