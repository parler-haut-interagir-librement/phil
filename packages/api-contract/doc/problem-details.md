# Problem details (RFC 9457)

**State: implemented and tested.** Class: `Phil\ApiContract\Problem\ProblemDetails`.

## The rule

An API has **one error format**: RFC 9457, media type `application/problem+json`.

- **The `type` is the contract; the `detail` is text for a human.** A client branches on `type` and, for a
  `type` it does not know, falls back on `status`. A new `type` is therefore an additive change.
- `detail` is written by the error handler for the reader of the response and is **never copied from an
  exception**: no stack trace, no query, no value the caller does not already hold. The library cannot enforce
  that; it keeps `detail` a plain string that the application fills in on purpose.
- The `type` URIs are the application's. The library declares none.

The class has two ways in, one per side of the contract: **the server writes strictly, the client reads
tolerantly.**

## Server side: `create()`

```php
public static function create(
    int $status,
    string $type = ProblemDetails::BLANK_TYPE,   // 'about:blank'
    ?string $title = null,
    ?string $detail = null,
    ?string $instance = null,
    array $extensions = [],
): ProblemDetails
```

`create()` throws `InvalidContractValue` when:

| Check | Accepted | Refused |
|---|---|---|
| `status` is an error status | `400` to `599` inclusive | `200`, `302`, `399`, `600` |
| `type` is `about:blank` or an **absolute** URI (scheme, colon, no whitespace) | `about:blank`, `https://example.com/errors/out-of-credit` | `/errors/out-of-credit` (relative), `example.com/errors` (no scheme), `''`, a URI with a space or a trailing newline |
| No extension shadows a standard member | `balance` | `type`, `status`, `title`, `detail`, `instance` |
| Extension names follow the RFC 9457 recommendation | a letter, then at least two letters, digits or underscores: `balance`, `retry_after` | `ab` (too short), `1abc` (starts with a digit), `out-of` (hyphen) |

RFC 9457 allows a relative `type`, resolved against the document's base. A stable contract should not depend on
where the document was fetched from, so the library accepts only an absolute URI.

```php
use Phil\ApiContract\Problem\ProblemDetails;

$problem = ProblemDetails::create(
    403,
    'https://example.com/errors/out-of-credit',
    'Out of credit',
    'Your balance is 30.',
    '/account/12345/msgs/abc',
    ['balance' => 30],
);

$response = new JsonResponse($problem->toArray(), $problem->status, [
    'Content-Type' => ProblemDetails::MEDIA_TYPE,
]);
```

`toArray()` returns the members ready to encode, in the order `type`, `status`, `title`, `detail`, `instance`,
then the extensions. **An absent member is left out rather than written as `null`**:
`ProblemDetails::create(404)->toArray()` is `['type' => 'about:blank', 'status' => 404]`.

## Client side: `fromArray()`

```php
public static function fromArray(array $members, int $responseStatus): ProblemDetails
```

Pass the decoded JSON document and the HTTP status of the response.

- **`status`**: the `status` member wins when it is an integer error status, because an intermediary may have
  changed the HTTP status and the member tells what the origin sent (`['status' => 429]` on a `502` reads as
  `429`). Otherwise the response status is used (`['status' => '429']` or `['status' => 200]` on a `503` reads as
  `503`). If neither is an error status, the document is not a problem and `InvalidContractValue` is thrown.
- **`type`**: kept when it is a string that is `about:blank` or an absolute URI; anything else (missing, not a
  string, relative) becomes `about:blank`, which leaves the status to decide.
- **`title`, `detail`, `instance`**: kept when they are strings, `null` otherwise. A member of the wrong JSON type
  is ignored, as RFC 9457 section 3.1 requires.
- **Extensions**: every other member with a string key is kept as is, **even when its name does not follow the
  recommendation** (`ab` is kept): the client is tolerant. Integer keys are dropped.

```php
$problem = ProblemDetails::fromArray(json_decode($body, true, flags: JSON_THROW_ON_ERROR), $statusCode);

if ($problem->hasType('https://example.com/errors/out-of-stock')) {
    // the case this client knows
} elseif ($problem->isBlank() || $problem->status >= 500) {
    // nothing more specific than the status: fall back on it
}
```

A document written by `create()` reads back equal through `fromArray($problem->toArray(), $problem->status)`.

## Public API

| Member | Description |
|---|---|
| `MEDIA_TYPE` | `'application/problem+json'` |
| `BLANK_TYPE` | `'about:blank'` |
| `$type`, `$status`, `$title`, `$detail`, `$instance`, `$extensions` | Public readonly properties. |
| `create(...)` | Strict constructor for the server. |
| `fromArray(array $members, int $responseStatus)` | Tolerant reader for the client. |
| `hasType(string $type): bool` | Exact comparison with the `type`. |
| `isBlank(): bool` | Whether the `type` is `about:blank`, that is, whether the client has nothing more specific than the status. |
| `toArray(): array` | The members, absent ones left out. |

## Not in the library

- No exception listener: rendering is the application's — or `phil/api-contract-bundle`'s, in a Symfony
  application.
- No catalogue of `type` URIs.
- No mapping from the library's own exceptions to problems: map `InvalidContractValue` usually to
  `400`, as your contract decides.
