# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, `array_any()`, `new` without parentheses. |
| `ext-sodium` | any | The manifest keys in URL-safe base64 and the scan identifiers in hexadecimal, in constant time. |
| `psr/clock` | `^1.0` | Scans are timed, credentials open and close, manifests expire, on a PSR-20 clock. |

## Installing

```sh
composer require phil/checkin
```

## Building the desk

The desk needs a resolver of codes, a ledger and a clock; the policy is optional. The resolver and the ledger are the
application's ([Extension points](extension-points.md)).

```php
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CredentialResolvers;

$desk = new CheckInDesk(
    new CredentialResolvers([$tickets, $registrations]),
    $ledger,
    $clock,
    new AdmissionPolicy(earlySeconds: 1800, antiPassback: true),
);
```

For offline devices, the manifest issuer and the synchronisation also need the catalog of the application:

```php
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineSync;

$issuer = new ManifestIssuer($catalog, $ledger, $clock, $policy, seconds: 43_200, limit: 10_000, labels: false);
$sync = new OfflineSync($catalog, $ledger, $clock, $policy, skewSeconds: 300, maxAgeSeconds: 172_800);
```

None of them keeps state between calls: every scan reads and writes the ledger. They are safe to share across the
requests of a long-running worker.

In Symfony, `phil/checkin-bundle` builds them from configuration, with a ledger kept by Doctrine.
