# Events

## What an event holds

| Part | Type | Bound |
|---|---|---|
| Identifier | 32 lower-case hexadecimal digits | random, 128 bits |
| Instant | `DateTimeImmutable` | from the factory's clock |
| Source | `Source`: `http`, `messenger`, `console`, `log`, `browser`, `manual` | |
| Severity | `Severity`: `warning`, `error`, `fatal` | |
| Exceptions | a non-empty list of `ExceptionRecord`, the one thrown first | 10 by default |
| Release, environment | texts or `null` | 128 and 64 characters |
| Request | `RequestSummary` or `null` | see below |
| Context | tags of scalars or `null` | 50 tags, keys of 64 characters, texts of 1,024 |

An `ExceptionRecord` has a type (255 characters), a message (2,048) and its frames, the innermost first. A `Frame`
has a file (1,024), a line, a function (255) and whether it is the application's. Every text is made valid UTF-8
before it is cut.

A `RequestSummary` has the method, the address (2,048), the route, at most 50 headers by lower-case name (1,024 each)
and the client's address as an anonymizer left it. It never holds the body. `RequestSummary::anonymized()` takes the
raw address and an `IpAnonymizer` of `phil/ip-anonymizer`, and keeps only what the strategy returns:

```php
use Phil\ErrorTracking\RequestSummary;
use Phil\IpAnonymizer\Truncation;

$request = RequestSummary::anonymized('GET', $url, 'order_show', $headers, $clientIp, new Truncation(24, 48));
```

## From a throwable

`EventFactory::fromThrowable()` reads the throwable, then each previous one, up to the bound of the chain. For each,
the first frame is where it was thrown and the function it was thrown in; the next ones are the calls that led there,
up to the bound of the frames. The arguments of the calls are never read, whatever `zend.exception_ignore_args` says.

The names PHP gives closures and anonymous classes carry a path, a line and, for an anonymous class, a NUL byte:
`{closure:App\Job::run():12}` becomes `{closure}` and `class@anonymous` loses everything after its name.

A file under the project directory is kept relative to it; it is the application's unless it lies under `vendor/`.
A file outside the project keeps its absolute path and is never the application's.

## From a message

`EventFactory::fromMessage()` builds an event of one exception from a type, a message and frames given by the
caller: a log record without exception, an error a browser reported. The frames are cut to the bound.

## Plain values

`toArray()` gives the event as scalars and arrays a store can write as JSON; `fromArray()` reads them back and throws
`InvalidEvent` on a missing value or one of another type. Unknown keys are ignored, and a tag or a header that is not a
scalar is dropped.

## Title and culprit

`title()` is the type and message of the exception thrown: `RuntimeException: The export failed`. `culprit()` is the
innermost frame of the application, looked for from the exception thrown to its causes.
