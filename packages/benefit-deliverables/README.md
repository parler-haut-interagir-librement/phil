# phil/benefit-deliverables

**Benefits owed to members each period as a checklist, with minimal shipping exports that expire, for subscriptions,
memberships and donations.**

phil/benefit-deliverables turns the benefits of a tier into deliverables: each benefit owed to each member for a
period, a postcard, a mention in the credits, a game with the game master, is a line of a checklist. The team checks
the lines off one by one or by lot, and reads who is still to be served. For a benefit that ships an object, it writes
the shipping export: a CSV file with only what the shipper needs, recorded in a journal, downloaded through a signed
link, and erased when it expires.

It is for a site whose members support it through tiers, by subscription, membership or donation, and owe them
something in return each period.

It is a plain PHP library. Who is owed what, what was delivered, where each member lives and where the files wait are
ports the application implements: the package depends on no module of subscriptions and keeps no address of its own.
The CSV file is written by `phil/spreadsheet`, which keeps as text a value a spreadsheet would run as a formula.

## Status

| Part | State |
|---|---|
| Benefits owed per period, read through a port | Implemented and tested |
| The checklist: lines by member, done one by one or by lot, who is still to be served | Implemented and tested |
| The shipping export: minimal CSV, journaled, signed link, file erased when it expires | Implemented and tested |
| The files in a local directory, `0600` | Implemented and tested |
| The port of a carrier's API | Interface only; no adapter |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The checklist](doc/checklist.md) | Periods, benefits, entitlements, the lines, marking them done, who is still to be served. |
| [Shipping exports](doc/shipping-exports.md) | What the file holds and never holds, the journal, the signed link, the expiry, the purge. |
| [Carriers](doc/carriers.md) | Handing a deliverable to a carrier through its API. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/benefit-deliverables
```

```php
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\Period;

$checklist = new Checklist($entitlementSource, $deliverableStore);
$page = $checklist->remaining(Period::of('2026-11'), 'postcard');

foreach ($page->items as $item) {
    // $item->key->member is still owed a postcard for November.
}

$checklist->markDone(Period::of('2026-11'), 'postcard', ['member-1', 'member-2'], $now, 'staff-7');
```

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`; `phil/address` and `phil/spreadsheet` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
