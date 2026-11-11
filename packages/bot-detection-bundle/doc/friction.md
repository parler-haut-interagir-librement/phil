# Friction

**A verdict is not a refusal.** A verdict is computed on the client, so it can be forged, and a false positive
must still get through. The strongest outcome of a verdict is therefore **one more step of friction**.

State: `Friction` and `FrictionPolicy` are implemented and tested.

## `Friction`

A pure enum with exactly two cases:

| Case | `adds()` | Meaning |
|---|---|---|
| `Friction::None` | `false` | The request proceeds as usual |
| `Friction::Step` | `true` | The request gets one more step of friction |

**There is deliberately no `Refuse` case**, and the tests assert that `Friction::cases()` is exactly
`[None, Step]`.

The application gives `Step` its meaning on each surface, for example:

- one more step of back-off on a sign-up rate limiter;
- a forced stronger authentication challenge, or one more step on a payment-attempt limiter;
- a hit that audience measurement does not count.

**The step never records why it was added.** It melts into the state of whatever it slows down (a limiter's
counter, a challenge requirement), and that state does not keep the verdict.

## `FrictionPolicy`

Maps a verdict to friction. The mapping is fixed by design except for one choice:

| Verdict | `robotOnly()` | `robotOrUnknown()` |
|---|---|---|
| `Human` | `None` | `None` |
| `Robot` | `Step` | `Step` |
| `Unknown` | `None` | `Step` |

```php
$policy = FrictionPolicy::robotOrUnknown();
$policy->unknownAddsFriction;              // true
$policy->frictionFor(Verdict::Unknown);    // Friction::Step

FrictionPolicy::robotOnly()->frictionFor(Verdict::Unknown); // Friction::None
```

The constructor is private: the two named constructors are the only policies.

### Choosing, surface by surface

| Policy | Cost | Use when |
|---|---|---|
| `robotOnly()` | A client can skip detection by sending nothing | Friction on a person is expensive (a payment), or the browser code is not deployed on the surface yet |
| `robotOrUnknown()` | Slows clients that never ran the detection script, and also people whose browser blocks scripts | Abuse is cheap and friction mild (a sign-up back-off) |

On a surface where the `verdict` controller is not placed, every verdict is `Unknown`: `robotOrUnknown()` then adds
a step to every request there.

## What the policy does not decide

**A browser that resists fingerprinting is not a robot.** Randomized or blanked signals, as privacy-focused
browsers produce them, must not trip a detector. That is a property of the detectors in the browser, not of this
policy: by the time a verdict reaches the server, it is already `robot` or `human`
([browser-signals.md](browser-signals.md)).

Declared crawlers (good bots announcing themselves in their user agent) are a server-side matter, decided from
the user agent with a crawler list such as `jaybizzle/crawler-detect`, not by this bundle.
