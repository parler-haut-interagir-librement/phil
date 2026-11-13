# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\ErrorTracking\ErrorEvent` | `final readonly class` | One error: `title()`, `culprit()`, `with()`, `toArray()`, `fromArray()` |
| `Phil\ErrorTracking\ExceptionRecord` | `final readonly class` | One exception of a chain: type, message, frames, `culprit()` |
| `Phil\ErrorTracking\Frame` | `final readonly class` | One frame: file, line, function, whether it is the application's, `label()` |
| `Phil\ErrorTracking\RequestSummary` | `final readonly class` | The reduced request: `anonymized()` |
| `Phil\ErrorTracking\CaptureContext` | `final readonly class` | Source, severity, request and tags of a capture |
| `Phil\ErrorTracking\Source` | enum | `http`, `messenger`, `console`, `log`, `browser`, `manual` |
| `Phil\ErrorTracking\Severity` | enum | `warning`, `error`, `fatal` |
| `Phil\ErrorTracking\EventFactory` | `final readonly class` | Events of an application: `fromThrowable()`, `fromMessage()`, `frame()` |
| `Phil\ErrorTracking\MessageNormalizer` | `final readonly class` | A message without its variable values: `normalize()` |
| `Phil\ErrorTracking\Fingerprinter` | `final readonly class` | The fingerprint of an event: `fingerprint()` |
| `Phil\ErrorTracking\Scrubber` | `final readonly class` | Personal data and secrets filtered: `scrub()`, `text()`, `url()`, `isSensitive()` |
| `Phil\ErrorTracking\Sampler` | `final readonly class` | A share of the events: `keep()` |
| `Phil\ErrorTracking\GroupStatus` | enum | `open`, `resolved`, `ignored`, `regressed`; `isUnresolved()` |
| `Phil\ErrorTracking\Occurrence` | enum | `first`, `regression`, `repeat`; `alerts()` |
| `Phil\ErrorTracking\ErrorGroup` | `final readonly class` | A group: `first()`, `seen()`, `resolve()`, `ignore()`, `reopen()` |
| `Phil\ErrorTracking\ErrorTracker` | `final readonly class` | The capture: `captureThrowable()`, `capture()` |
| `Phil\ErrorTracking\Capture` | `final readonly class` | What became of a kept event |
| `Phil\ErrorTracking\Contract\ErrorStore` | interface | Where the groups and events are kept: `find()`, `save()` |
| `Phil\ErrorTracking\Contract\CaptureThrottle` | interface | The events of a key bounded: `allow()` |
| `Phil\ErrorTracking\Contract\GroupAlerter` | interface | A first event or a regression told: `alert()` |
| `Phil\ErrorTracking\Exception\ErrorTrackingException` | interface | Every exception of the library |
| `Phil\ErrorTracking\Exception\InvalidEvent` | `final class` | An event built or read with a value it cannot hold |
| `Phil\ErrorTracking\Exception\InvalidOption` | `final class` | A sample rate or a bound out of range |
