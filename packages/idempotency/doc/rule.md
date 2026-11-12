# The rule

## Keys

`IdempotencyKey` is the key of one intention. **On the client, one key per intention, kept from one retry to the
next**: a key generated per attempt makes every retry a new request, which is the duplicate the key exists to
prevent. Generate it where the intention is born — `IdempotencyKey::generate()`, a random UUIDv4 — and carry it in
the message or the job that retries.

| Member | Description |
|---|---|
| `HEADER` | `'Idempotency-Key'` |
| `MAX_LENGTH` | `255` |
| `generate(): self` | A random UUIDv4, 122 bits from a CSPRNG. |
| `fromString(string $value): self` | 1 to 255 bytes of printable ASCII, without `"`, `\` or surrounding spaces; inner spaces are allowed. |
| `fromHeader(?string $header): self` | Trims the header; throws `MissingIdempotencyKey` when it is absent or blank; strips one pair of surrounding quotes; then applies `fromString()`. |
| `toHeaderValue(): string` | The quoted form, `"abc-123"`. |
| `equals(self $other): bool` | Exact comparison. |

The header is specified by the IETF draft `draft-ietf-httpapi-idempotency-key-header`, which writes the value as a
structured-field string, quoted; many clients send it bare. The reader accepts both, the writer sends the quoted
form.

## Scopes

`IdempotencyScope::of(string $value)` says whose keys these are: an API client, a session, a webhook sender, a bus.
**Keys are never global**: in a shared namespace, one caller's key suppresses another caller's request, and neither
side sees an error. A scope is 1 to 255 bytes without control characters.

## Fingerprints

`PayloadFingerprint` is the SHA-256 of what the operation was asked to do, kept next to the key **instead of the
payload**.

- `ofRequest(string $method, string $target, string $body)` covers the upper-cased method, the path and query, and
  the raw body, byte for byte. Reusing a key on another endpoint is a reuse; a client that re-encodes the body
  between attempts is refused, the safe answer when the server cannot tell whether the change matters.
- `ofParts(string ...$parts)` covers any other operation: a message class, the event type and identifier of a
  webhook, the submitted fields of a form. Choose the parts that say what the operation does, and leave out what
  changes from one attempt to the next, such as a timestamp of the client.
- Each part is prefixed by its byte length and a colon before hashing, so no choice of parts collides with another
  by concatenation: `ofRequest('post', '/api/things', '{"a":1}')` hashes `4:POST11:/api/things7:{"a":1}`. **This
  layout is pinned by a test**: fingerprints are stored, and changing it would turn every stored key into a reuse.
- `fromHex()` reads one back from storage; `equals()` compares in constant time.

## The three states

| `IdempotencyState` | Meaning | Expires |
|---|---|---|
| `InProgress` | Reserved, the run is going. | After the reservation: longer than the slowest run, far shorter than the retention. A process killed mid-run keeps its key that long only. |
| `Completed` | Answered: the `StoredResponse` is replayed. | After the retention. |
| `Failed` | The run failed in a way the caller may retry. | After the retention. |

An `IdempotencyRecord` holds the fingerprint, the state, the expiry, and the response of a completed run.
`secondsLeft($now)` gives the whole seconds left before its expiry, at least 1: the `Retry-After` of a run in
progress.

`StoredResponse::of(int $status, array $headers = [], string $body = '')` keeps what the replay needs: a status
between 100 and 599, the headers chosen for the replay, the body. `StoredResponse::nothing()` is the `204` of an
operation that returns nothing. What the body holds and how long it is kept belong in the application's retention
schedule.

## The four outcomes

`IdempotencyDecision::for(?IdempotencyRecord $existing, PayloadFingerprint $fingerprint)` is a pure function:

| Store holds | Fingerprint | Outcome | HTTP answer |
|---|---|---|---|
| nothing | any | `Proceed` | Run, then keep the answer. |
| a record | different | `Reused` | `422` problem. Returning the first result would silently drop a corrected request. |
| in progress | same | `InProgress` | `409` problem with `Retry-After`. |
| failed | same | `Proceed` | Run again. |
| completed | same | `Replay` | The stored response. |

**The fingerprint is compared before the state**, so a caller learns of its mistake on the first retry, even while
the first run is still going or after it failed. `$decision->existing` is the record the decision read, and
`$decision->replay()` the response to return on `Replay`.

The HTTP answers are those of the IETF draft of the `Idempotency-Key` header: `422` to a key reused with another
payload, which no retry will fix, and `409` to a key whose first run is still going, which a retry after `Retry-After`
may.

Next: [the guard](guard.md).
