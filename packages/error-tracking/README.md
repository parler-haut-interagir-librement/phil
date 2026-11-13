# phil/error-tracking

**The errors of an application, kept at home: grouped, scrubbed of personal data, and told apart as new, repeated or
back again.**

phil/error-tracking turns an exception into an error event — its chain of exceptions, the frames of each trace, the
context of the code that caught it, the release and environment that ran it, and the request reduced to what helps —
and files it in a group of the events that share its fingerprint. The fingerprint is stable: the type of each
exception, its message without the values that change, and the file and function of the application's first frames,
without their lines. A group is open, resolved, ignored, or regressed when it comes back on another release after it
was resolved.

Nothing is written before the scrubber has run: the `Authorization` and `Cookie` headers, the fields named like
passwords and tokens, every value of the query and the e-mail addresses in any text are filtered, and the client's
address is only ever kept as `phil/ip-anonymizer` left it. A sampler keeps a share of the events and a throttle bounds
the events of one group, so that an error thrown in a loop costs a bounded number of writes.

It is for any PHP application that wants its errors where its data already lives, without sending them to a third
party: a small site, a shop, a game tool in its first phase. It is a plain PHP library whose
requirements are `psr/clock` and `phil/ip-anonymizer`. Where the groups are kept is a port, `ErrorStore`, and so are
the throttle and the alerts; `phil/error-tracking-bundle` brings the capture, a DBAL store, the administration pages
and the alerts to a Symfony application.

## Status

| Part | State |
|---|---|
| Error events: chained exceptions, frames without arguments, context, release, environment, reduced request | Implemented and tested |
| The stable fingerprint and the message normalizer | Implemented and tested, with a benchmark |
| The scrubber of headers, fields, query values, e-mail addresses, credentials and tokens | Implemented and tested, with a benchmark |
| The sampler, the throttle port | Implemented and tested |
| The statuses of a group: open, resolved, ignored, regressed on another release | Implemented and tested |
| `ErrorTracker` over a store, a throttle and an alerter | Implemented and tested |
| Source maps of minified scripts, and the frames read back through them | Not started, planned after the first release: a frame keeps the address and line it was given |
| A Symfony bundle: capture, DBAL store, administration pages, alerts, browser errors | Implemented in `phil/error-tracking-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building the tracker. |
| [Events](doc/events.md) | What an event holds, its bounds, and how a throwable becomes one. |
| [Grouping](doc/grouping.md) | The fingerprint, the message normalizer and the statuses of a group. |
| [Scrubbing](doc/scrubbing.md) | What is filtered before anything is written, and how to add fields. |
| [Sampling and throttling](doc/sampling-and-throttling.md) | The sampler and the throttle port. |
| [Extension points](doc/extension-points.md) | The store, the throttle and the alerter of the application. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/error-tracking
```

```php
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Source;
use Symfony\Component\Clock\NativeClock;

$tracker = new ErrorTracker(new EventFactory('/srv/app', new NativeClock(), release: '1.4.0', environment: 'prod'), $store);

try {
    $exporter->run($order);
} catch (ExportFailed $failure) {
    $capture = $tracker->captureThrowable($failure, new CaptureContext(Source::Manual, context: ['order' => $order->id]));
    // $capture->occurrence: First, Regression or Repeat; null when sampled out or throttled
}
```

`$store` is the application's `ErrorStore`: see [Extension points](doc/extension-points.md).

## Requirements

PHP 8.5 or later with `mbstring` and `sodium`, `psr/clock` and `phil/ip-anonymizer`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
