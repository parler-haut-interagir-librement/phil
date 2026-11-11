# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses. |
| clamd | any supported ClamAV | For `ClamdScanner`: the daemon that scans, on a Unix socket or a TCP port. |

The library needs no PHP extension beyond those of a default build, and no third-party client of ClamAV.

## Installing

```sh
composer require phil/upload-scan
```

## Building a scanner

```php
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Clamd\SocketConnector;

$scanner = ClamdScanner::at('unix:///run/clamav/clamd.ctl');

// Or every setting given:
$scanner = new ClamdScanner(
    new SocketConnector('tcp://clamav:3310', connectTimeoutSeconds: 2.0),
    timeoutSeconds: 30.0,
    maxBytes: 26_214_400,
    chunkBytes: 65_536,
);
```

A scanner keeps nothing between two files: it opens one connection per file and closes it once answered, so a single
instance serves every request of a long-running worker.

In Symfony, `phil/upload-scan-bundle` builds the scanner from configuration and adds a validation constraint, a
quarantine and events.
