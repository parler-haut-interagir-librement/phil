# phil/checkin

**The check-in of an event or a venue: a code read at a door, decided at once, recorded once, online or offline.**

phil/checkin is the core every kind of check-in shares. A device of the staff reads a code at a checkpoint — a door,
a gate, a room, one session of a programme —; the code is resolved to its credential — a ticket, a registration, a
membership card, a badge —; an admission policy decides on the credential's attendance whether its holder goes in;
and the decision is recorded in a ledger every device shares, refusals included, under the scan's own identifier, so
that a scan sent twice is recorded once.

It is for any application that lets people in: a convention that checks tickets at several doors, a club that counts
its members' visits, a course that takes attendance, a venue that lets people out and back in. What a credential is
belongs to the application, through one port; how a code is carried — printed, typed, in a QR code, signed — belongs
to the packages that extend the core.

Devices keep checking in when the network goes down. An offline manifest gives a device the credentials of its
checkpoint under keyed fingerprints of their codes, never the codes themselves; the device decides with the same
policy, keeps a journal, and sends it back once online, where each scan is decided again, recorded with the decision
the holder met at the door, and flagged as a conflict when the ledger decides otherwise.

It is a plain PHP library whose only requirements are `psr/clock` and the sodium extension.

## Status

| Part | State |
|---|---|
| Identifiers, codes and their fingerprints, credentials, attendance, check-ins | Implemented and tested |
| The admission policy: opening with an early window, closing, entries, anti-passback, check-out and re-entry | Implemented and tested |
| `CheckInDesk`: resolve, decide, record once per scan identifier, decide again after a race | Implemented and tested |
| Offline manifests of keyed fingerprints, read of the journals, synchronisation with conflicts | Implemented and tested |
| An in-memory ledger for tests | Implemented and tested |
| A Symfony bundle: a Doctrine ledger, the endpoints, the desk's Stimulus controller with its offline journal | Implemented in `phil/checkin-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building the desk. |
| [Checking in](doc/checking-in.md) | Codes, credentials, the admission policy, attendance, the desk and its races. |
| [Offline](doc/offline.md) | Manifests, fingerprints, journals, the synchronisation and its conflicts. |
| [Extension points](doc/extension-points.md) | The resolvers, the ledger and the catalog of the application. |
| [Exceptions](doc/exceptions.md) | `CheckinException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar, fuzz target and benchmark, and how to test a check-in. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/checkin
```

```php
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\ScanOutcome;

$desk = new CheckInDesk($tickets, $ledger, $clock, new AdmissionPolicy(earlySeconds: 1800));

$result = $desk->scan(CredentialCode::of($scanned), CheckpointId::of('main-door'), DeviceId::of('door-tablet-2'));

if (ScanOutcome::Admitted === $result->outcome) {
    // green light, and $result->credential->label for the staff
}
```

`$tickets` is the application's `CredentialResolver` and `$ledger` its `CheckInLedger`: see
[Extension points](doc/extension-points.md).

## Requirements

PHP 8.5 or later, the sodium extension, and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
