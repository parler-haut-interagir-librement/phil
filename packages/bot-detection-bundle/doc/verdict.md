# The verdict

**The browser reports one thing, and only one: robot or human.** No identifier, no fingerprint, no hash, no
detector detail leaves the browser.

State: `Phil\BotDetectionBundle\Domain\Verdict\Verdict` is implemented and tested. The browser computes it with the
`verdict` Stimulus controller and its detectors ([browser-signals.md](browser-signals.md)).

## `Verdict`

A string-backed enum:

| Case | Value | Meaning |
|---|---|---|
| `Verdict::Human` | `human` | The browser's detectors found no automation |
| `Verdict::Robot` | `robot` | The browser's detectors found automation |
| `Verdict::Unknown` | `unknown` | No usable verdict: the field was absent, empty, or held anything other than the two wire values |

`Unknown` is where a browser that blocks scripts lands, as does a client that never ran them, and a tampered
value.

### Reading what the client submitted: `fromSubmitted()`

```php
$verdict = Verdict::fromSubmitted($request->request->get('verdict')); // ?string

Verdict::fromSubmitted('human');        // Human
Verdict::fromSubmitted('robot');        // Robot
Verdict::fromSubmitted(null);           // Unknown — absent
Verdict::fromSubmitted('');             // Unknown
Verdict::fromSubmitted('Robot');        // Unknown — another case
Verdict::fromSubmitted(' human ');      // Unknown — surrounding whitespace
Verdict::fromSubmitted('human:3f2a9c'); // Unknown — a longer payload
Verdict::fromSubmitted('true');         // Unknown
Verdict::fromSubmitted('unknown');      // Unknown — the unknown case is not a wire value
```

**Parsing is strict, and never throws.** Only the exact wire values `human` and `robot` are recognized. A verdict
that fails to parse is no reason to refuse a request, so there is no exception to catch. Strictness also means a
client cannot smuggle more than a verdict behind one (`human:<something>`): the shape an identifier would take is
refused as `Unknown`.

Use `fromSubmitted()` rather than `Verdict::from()` or `tryFrom()`: `from('unknown')` would accept the internal
case as if a client had sent it, and `from()` throws on anything else.

### `isRobot()`

```php
Verdict::Robot->isRobot();   // true
Verdict::Human->isRobot();   // false
Verdict::Unknown->isRobot(); // false
```

## Invariants

- **A verdict is not an identifier.** It carries nothing that could single anyone out.
- **A verdict is never stored** — not with the request it decides, not in the session, not next to any device
  fingerprint the session holds. It decides one request and is dropped
  ([verdict-handling.md](verdict-handling.md)).
- **The only trace is an aggregate count of robot verdicts** (`RobotVerdictCounter`), which takes no argument.
- **A verdict is forgeable.** It is computed by code running on the client: a robot can send `human`, and a
  person's browser can be wrongly flagged. That is why a verdict only ever adds friction, and never refuses
  ([friction.md](friction.md)).
- **A missing verdict is not a robot verdict.** It is `Unknown`; whether it adds friction is a per-surface choice,
  and it is never counted as a robot.
