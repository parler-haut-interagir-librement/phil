# Testing

## What the library's own tests cover

The unit suite lives in `tests/Unit` and runs with PHPUnit 13. It needs no database, no kernel and no network.

| Test class | What it pins |
|---|---|
| `ProblemDetailsTest` | Strict writing (error status, absolute `type`, extension names, no shadowing), absent members left out, tolerant reading (wrong JSON types ignored, relative `type` read as blank, `status` member against response status), round trip. |
| `DeprecationSignalTest` | The three headers written, absent parts omitted, a sunset that precedes the deprecation refused, links that would break the header refused, malformed values ignored on reading (including the pre-RFC `Deprecation: true`), structured-field parameters dropped, contradictory dates keeping the sunset, `noticeInDays()` rounding, round trip with fractional seconds. |
| `LinkHeaderTest` | Quoted and bare `rel`, case-insensitivity, several relations, several entries and lines, first `rel` only, a `rel` inside a quoted parameter ignored, empty target. |
| `HttpDateTest` | UTC IMF-fixdate on writing; the three forms of RFC 9110 section 5.6.7 for the same instant (`784111777`); impossible days, wrong weekdays, other zones and trailing text refused. |
| `TolerantEnumTest` | Defaults and refusals for string and int backed enums, no coercion, the bounded and escaped message. |
| `OpenApiDocumentTest` | JSON objects read, lists and scalars refused, the bounds of size, depth and values to the exact limit, JSON Pointers, local references and chains of them up to sixteen, operations in order, parameters overridden by the operation's, responses resolved, the walk without examples and extensions. |
| `ContractVerifierTest` | The levels and what each holds, the severities, the built-in rules by level, findings in rule order, `fails()` and `reaching()`, rules left out by id, rule ids refused, findings kept printable and bounded. |
| `MinimalRulesTest`, `RecommendedRulesTest`, `StrictRulesTest` | Each rule's level, severity and description; the documents it lets through and the exact location and message of each finding it yields. |

### Reference values

- RFC 9110 section 5.6.7's example instant, `Sun, 06 Nov 1994 08:49:37 GMT`, in its three forms, is Unix time
  `784111777`.
- RFC 9457's example problem (`out-of-credit`, balance 30, instance `/account/12345/msgs/abc`) is used as the
  full-document fixture.

## Running the tests

From the root of the monorepo:

```bash
vendor/bin/phpunit packages/api-contract/tests
```

Installed on its own, in a fresh copy of the library:

```bash
cd api-contract
composer install
vendor/bin/phpunit --no-configuration tests
```

Infection keeps the library at 100 % of mutants killed.

## Testing your adapters

The port is small enough to test against its contract directly.

**`ObservedDeprecations`**: assert that the first `record()` returns `true`, a second within the day returns
`false`, a third a day later returns `true`, and that `all()` holds the latest signal per endpoint.

**`OpenApiRule`**: verify documents built as arrays with `OpenApiDocument::fromArray()`, and assert the exact location
and message of each finding, as `MinimalRulesTest` does; include values of the wrong type where the rule expects an
object or a string.

**Your HTTP integration**: write responses with `DeprecationSignal::toHeaders()` and `ProblemDetails::toArray()`
and read them back with `fromHeaders()` and `fromArray()`; include unknown fields, unknown enumeration values
and an unknown problem `type` in your contract fixtures, and assert that the client degrades as
[tolerant-reading.md](tolerant-reading.md) describes.

## Fuzzing

The fuzz target `openapi-verifier`, in `tests/Fuzz`, gives the verification at the strict level inputs nobody wrote:
whatever it reads, it returns a report or refuses the document as unreadable, and never throws anything else nor warns.
Its seeds are in `tests/Fuzz/corpus/openapi-verifier`. From the root of the monorepo:

```sh
composer fuzz -- openapi-verifier
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/api-contract/tests/Benchmark
```
