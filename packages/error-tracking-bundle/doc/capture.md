# Capture

The capture points below are the bundle's interface: an application reports its errors through them, and through
`SafeCapture` for its own calls. They need no SDK and read no third-party format; an application moving from a Sentry
SDK replaces it with them, as [Migrating from sentry-symfony](migrating-from-sentry.md) shows.

## The capture points

| Point | Service | Source | Tags |
|---|---|---|---|
| The exception a request ends in, `kernel.exception` at priority 128 | `ExceptionListener` | `http` | the reduced request |
| A message a worker failed, `WorkerMessageFailedEvent` | `WorkerFailureListener` | `messenger` | `message`, `transport`, `will_retry` |
| The error a command ends in, `console.error` | `ConsoleErrorListener` | `console` | `command`, `exit_code` |
| A log record of the handler's level and above | `phil_error_tracking.monolog_handler` | `log` | `channel` |
| A browser's report | `BrowserErrorController` | `browser` | the page and the `User-Agent` |
| The application's own call | `SafeCapture::throwable()` or `ErrorTracker` | `manual` by default | its own |

The request listener runs before the kernel logs the exception, so that the event carries the request; the log
record of the same exception that follows is not captured again.

An HTTP exception below 500 — a 404, a 403 — is left out unless `capture.http.client_errors` is true. A failed message
is captured once it will not be retried, unless `capture.messenger.retries` is true; the exception its handler threw is
captured rather than Messenger's `HandlerFailedException`, so that it groups as it would in a request. The arguments of
a command are never kept.

The Monolog handler captures the exception of the record's `exception` key, or else the record itself, as an event of
type `Log record` whose message is the record's — with its placeholders when no processor replaced them, so that
`Order {id} failed` groups as one. `critical` and above are fatal, `error` is an error, below is a warning.

A processor that runs before the handlers may have replaced the exception by an array of its `class`, `message`,
`file`, `line` and `previous` exception, as the log redaction of `phil/privacy-bundle` does. The handler reads that
array as the chain it describes, each exception with the one frame of its file and line, so that the event keeps its
class and its place and groups by them rather than as a `Log record`. The record of an exception the request listener
already captured is still left out: the class, file and line of each throwable captured are remembered until the
services are reset, and the first array that matches one is taken for it.

## Arguments in messages

A frame keeps its file, line and function, never the values of its arguments. PHP 8.6 adds `error_include_args`, off by
default: on, PHP writes the arguments of the calls into the messages of its errors, except the parameters marked
`#[\SensitiveParameter]`. A message is captured as it is, so an argument PHP wrote there — an address, a token the
code forgot to mark — reaches the store, where only the scrubber's patterns stand in its way. Keep
`error_include_args` off in production, and mark the parameters that carry secrets `#[\SensitiveParameter]` whatever
its value.

## The guard

`SafeCapture` is the only way into the tracker for the capture points:

- it never throws: a failed capture — the database is down, the chat is unreachable — returns `null`;
- while a capture runs, any other capture is refused: the warning that logs a failed capture reaches the Monolog
  handler, which asks for a capture, which is refused, and the loop never starts;
- the failure is logged once, as a warning that names its class only;
- a throwable already captured is not captured again until the services are reset, at the end of each request of a
  worker (`kernel.reset`); nor is the first exception a log processor turned into an array with its class, file and
  line;
- the classes of `ignored_exceptions` and their subclasses are never captured.

## Your own captures

```php
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTrackingBundle\Application\SafeCapture;

public function __construct(private SafeCapture $capture) {}

try {
    $this->exporter->run($order);
} catch (ExportFailed $failure) {
    $this->capture->throwable($failure, new CaptureContext(context: ['order' => $order->id]));
}
```
