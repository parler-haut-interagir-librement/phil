# phil/broken-flow-alerts

**A warning on the day a path your visitors finish stops being finished.**

phil/broken-flow-alerts watches the flows of a site — a purchase, a registration, a contact form, any path whose last
step the application already counts — and warns when one is completed far less often than its rhythm leads to expect.
A payment provider that changed its answer, a button that no longer submits, a redirect that loops: the visitors keep
coming, and the completions stop. The library notices from the daily counts, compares the last days with the weeks
before, weekday by weekday, and sends an alert on the day the flow breaks, then another on the day it completes again.

It is **passive**: it reads counts the application keeps anyway, such as the goals of an analytics tool, and sends no
request to the site, synthetic or otherwise. Nothing about a visitor reaches it: a count per flow and per day is all
it reads.

The decision is a Poisson count. The baseline days set the expected completions of each day of the window; for each
stretch that ends on the last whole day, the library asks how likely so few completions would be in a working flow, and
calls the flow broken when the answer is under the significance, 0.001 by default. A busy flow is broken after a single
silent day, a flow completed twice a day after about four, and a flow whose usual day is close to zero is never broken:
a silent day is ordinary there.

It is a plain PHP library: its only requirement is `psr/clock`. `NotifierAlertSender` sends the alerts through
Symfony Notifier when `symfony/notifier` is installed; any other channel is an `AlertSender` of the application. With
`phil/analytics-bundle`, the goals of the sites are watched from the bundle's configuration.

## Status

| Area | State |
|---|---|
| Flows (`Flow`), their history (`CompletionHistory`) | Implemented and tested |
| The rule (`BrokenFlowRule`, `FlowStatus`): baseline, window, weekday rhythm, Poisson tail with a bounded cost | Implemented and tested |
| The watch (`BrokenFlowWatch`): an alert on the day a flow breaks and on the day it recovers, without a store | Implemented and tested |
| Alerts (`FlowAlert`, `FlowAlertKind`) and their sending (`AlertSender`, `NotifierAlertSender`) | Implemented and tested |
| Synthetic checks (requests sent to the site) | Not part of the library |

## Documentation

- [Installation](doc/installation.md) — requirements, installing, and the optional Symfony Notifier.
- [Detection](doc/detection.md) — the baseline and the window, the weekday rhythm, the Poisson count, the settings and
  what they change.
- [The watch and its alerts](doc/watch.md) — the last whole UTC day, the alert on the day a flow breaks and on the day
  it recovers, the history asked for, and the notifications.
- [Extension points](doc/extension-points.md) — `CompletionHistory` and `AlertSender`, with example adapters.
- [Testing](doc/testing.md) — running the tests, and testing an adapter.
- [API reference](doc/reference.md) — every public type, with its role.

## Quick start

```bash
composer require phil/broken-flow-alerts:^0.1@dev   # from a path or VCS repository until published
```

```php
use Phil\BrokenFlowAlerts\{BrokenFlowWatch, Flow};
use Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender;

$watch = new BrokenFlowWatch($history, new NotifierAlertSender($notifier), $clock);

// Once a day: the alerts of the flows that broke or recovered on the last whole UTC day
$alerts = $watch->check([Flow::of('checkout', 'Ticket sales'), Flow::of('contact', 'Contact form')]);
```

`$history` is the application's `CompletionHistory`: one count per UTC day for a flow, read from its counters.

## Requirements

- PHP 8.5 or later
- `psr/clock` 1.0
- `symfony/notifier` 8.2, optional, for `NotifierAlertSender`

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
