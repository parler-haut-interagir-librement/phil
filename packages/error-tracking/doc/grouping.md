# Grouping

## The fingerprint

`Fingerprinter::fingerprint()` is the SHA-256, in hexadecimal, of each exception of the chain in order:

1. its type;
2. its message, through the `MessageNormalizer`;
3. the file and function of its first ten frames of the application, without their lines.

Two events share a group when all of it is equal. Lines are left out so that an edit elsewhere in a file keeps the
group; the frames of the dependencies are left out so that an upgrade keeps the groups of the application. An event
without any frame of the application is grouped by its types and messages.

## The message normalizer

`MessageNormalizer::normalize()` reads the first 512 characters of a message and replaces, in this order:

| What | Becomes |
|---|---|
| a UUID | `<uuid>` |
| an e-mail address | `<email>` |
| an address with a scheme, `https://…` | `<url>` |
| a text between double or single quotes | `<string>` |
| `0x…`, or twelve hexadecimal digits or more with a digit among them | `<hex>` |
| a decimal number | `<n>` |

`User 42 not found` and `User 43 not found` both read `User <n> not found`.

## The statuses of a group

An `ErrorGroup` holds the fingerprint, the title and culprit of its first event, its `GroupStatus`, how many times it
was seen, its first and last instants, the last release seen, and the release it was resolved on.

| From | Event or action | To |
|---|---|---|
| none | first event | `open` — `Occurrence::First` |
| `open`, `regressed` | event | unchanged — `Repeat` |
| any | `resolve()` | `resolved`, on the last release seen |
| `resolved` | event of the release it was resolved on | `resolved` — `Repeat` |
| `resolved` | event of another release, or either release unknown | `regressed` — `Regression` |
| any | `ignore()` | `ignored`; events are counted, the status stays |
| any | `reopen()` | `open` |

An event of the release a group was resolved on comes from code still running the old way, a worker not restarted
yet: it leaves the group resolved. `GroupStatus::isUnresolved()` is true for `open` and `regressed`.
`Occurrence::alerts()` is true for `First` and `Regression`.

## The tracker

`ErrorTracker` captures in this order: the sampler, the scrubber, the fingerprint, the throttle, the group read from
the store and moved to its next state, the group written with the event, and the alerter for a first event or a
regression. `captureThrowable()` and `capture()` return a `Capture` — the event's identifier, the fingerprint, the
occurrence and the group's status — or `null` when the sampler or the throttle left the event out.

An exception of the store or of the alerter reaches the caller: the code that catches errors decides what a failed
capture does, and must not throw one of its own.
