# The payment attempt limiter

The limiter slows down repeated payment attempts, per order and per client network, with **a single state per key** and
no memory of why an attempt was slowed. It is written as pure functions; the application stores the states and decides
what a robot verdict triggers.

| Type | Role |
|---|---|
| `Domain\ValueObject\PaymentAttemptPolicy` | The budget, the penalty step and the window |
| `Domain\ValueObject\PaymentAttemptKey` | The key of one state: an order, or a client network |
| `Domain\ValueObject\PaymentAttemptState` | The one state of a key: attempts, cumulative wait, last attempt |
| `Domain\ValueObject\PaymentAttemptDecision` | Allowed now, or retry at a time; never a reason |
| `Domain\Service\PaymentAttemptLimiter` | `decide()` and `record()` over one key's state |
| `Application\Port\PaymentAttemptStateStore` | Port: stores one state per key with a time-to-live |

## The rules

- **A robot verdict adds friction and never refuses on its own.** A robot verdict can be forged and can be wrong;
  a false positive must still be able to pay. The application either forces a stronger authentication challenge
  on the attempt (3-D Secure, where its processor allows it), or records the attempt **with a penalty**, which
  adds one step of wait. The processor's own risk control makes the decision on the payment.
- **The buyer sees a wait, never a refusal that names a verdict.** A decision carries a retry time and nothing
  else.
- **Each key holds one state, and only one.** A penalty melts into the cumulative wait: there is no separate
  penalty counter, and the verdict is never stored. The state cannot tell why its wait went up.
- **The state expires one window after its last attempt**, and the store must honour that time-to-live: a client
  IP address is personal information, and the window bounds how long it is kept.
- **Both keys are evaluated, the more restrictive decision wins.** An order and the client network each have their
  state.
- **An attempt made during a wait is not recorded**, so retrying while waiting (a buyer refreshing, a neighbour
  behind the same address) never pushes the wait back.
- **Robot verdicts are counted in aggregate only, and not here**: the verdict comes from `phil/bot-detection-bundle`,
  whose `VerdictHandler` counts it and returns the friction to apply (see below).

## The verdict at payment

The bundle does not read verdicts: the payment flow hands the verdict the browser sent to the `VerdictHandler` of
`phil/bot-detection-bundle`, wired for payment with its own `RobotVerdictCounter`, and turns the friction it returns
into a penalty — or a stronger authentication challenge ([below](#a-payment-attempt-end-to-end)). Nothing
identifying reaches that counter, and the verdict goes no further. How to wire one handler per surface is in that
bundle's `doc/verdict-handling.md`.

## `PaymentAttemptPolicy`

| Member | Behaviour |
|---|---|
| `static of(int $budget, int $penaltyStepSeconds, int $windowSeconds): self` | Validates the three values |
| `public int $budget` | Attempts a key may make within its window |
| `public int $penaltyStepSeconds` | Wait one penalised attempt adds |
| `public int $windowSeconds` | Time-to-live of a state, renewed by each attempt |
| `longestCumulativeWaitSeconds(): int` | `budget × step` |

Refusals, all `InvalidValue`: a budget below 1; a step below one second; `budget × step` beyond a native integer;
**a window that does not exceed `budget × step`**. A window equal to the longest wait would let a state expire at
the very moment its last penalty ends, a penalty that never slows anything down.

The three values are the application's decision; the bundle has no defaults.

## `PaymentAttemptKey`

| Member | Behaviour |
|---|---|
| `static forOrder(string $orderId): self` | Value `order:<id>`. Refuses an empty identifier, surrounding whitespace, a control character (C0 or DEL), or more than 255 bytes |
| `static forClientIp(string $ip, int $ipv6PrefixLength = 64): self` | Value `ip:<network>`. Refuses anything that is not an IP address (a hostname, an address with a port), and a prefix length outside 1 to 128 |
| `const DEFAULT_IPV6_PREFIX_LENGTH = 64` | |
| `public string $value` | The store key |
| `equals(PaymentAttemptKey $other): bool` | Same value |

How a client address becomes a key:

| Input | Key |
|---|---|
| `203.0.113.7` | `ip:203.0.113.7` (the prefix length is ignored for IPv4) |
| `::ffff:203.0.113.7`, an IPv4-mapped address | `ip:203.0.113.7` |
| `2001:0DB8:0000:0000:0000:0000:0000:0001` | `ip:2001:db8::/64` |
| `2001:db8::1` | `ip:2001:db8::/64` |
| `2001:db8:0:1:ffff:ffff:ffff:ffff` | `ip:2001:db8:0:1::/64` |
| `2001:db8:abcd:1234::1` with prefix 48 | `ip:2001:db8:abcd::/48` |

- **An IPv6 key is the client's network, not its address.** An IPv6 client holds at least a /64 and can change
  address on every attempt; a new address must not buy a new budget.
- **Addresses are normalised through their binary form**, so two spellings of one address share one state.
- **Order and IP keys never collide**: their prefixes differ.
- Pass the client address as your trusted proxy configuration resolves it (`Request::getClientIp()` with
  `trusted_proxies` set), never a raw `X-Forwarded-For` header.

## `PaymentAttemptState`

| Member | Behaviour |
|---|---|
| `static of(int $attempts, int $cumulativeWaitSeconds, DateTimeImmutable $lastAttemptAt): self` | For reading a stored state back. Refuses fewer than one attempt and a negative wait (`InvalidValue`) |
| `public int $attempts`, `public int $cumulativeWaitSeconds`, `public DateTimeImmutable $lastAttemptAt` | The three fields, and nothing else |
| `expiresAt(PaymentAttemptPolicy $policy): DateTimeImmutable` | Last attempt plus the window |
| `isExpiredAt(DateTimeImmutable $now, PaymentAttemptPolicy $policy): bool` | `$now` at or after `expiresAt()` |
| `nextAttemptAllowedAt(): DateTimeImmutable` | Last attempt plus the cumulative wait |

## `PaymentAttemptDecision`

| Member | Behaviour |
|---|---|
| `static allowed(): self` | The attempt may proceed now |
| `static waitUntil(DateTimeImmutable $retryAt): self` | The attempt waits |
| `public ?DateTimeImmutable $retryAt` | `null` when allowed |
| `isAllowed(): bool` | |
| `retryAfterSeconds(DateTimeImmutable $now): int` | The value of a `Retry-After` header: whole seconds, rounded up; zero when allowed or when the retry time has passed |
| `static mostRestrictive(self $first, self ...$others): self` | The latest retry wins; any wait beats an allowance; on a tie the first is kept |

## `PaymentAttemptLimiter`

```php
final readonly class PaymentAttemptLimiter
{
    public function __construct(PaymentAttemptPolicy $policy);
    public function decide(?PaymentAttemptState $state, DateTimeImmutable $now): PaymentAttemptDecision;
    public function record(?PaymentAttemptState $state, DateTimeImmutable $now, bool $penalise): PaymentAttemptState;
    public function timeToLiveSeconds(): int; // the policy's window
}
```

`decide()`:

| State | Decision |
|---|---|
| None, or expired | allowed |
| Budget spent (`attempts >= budget`) | wait until the state expires |
| Otherwise, before `lastAttemptAt + cumulativeWait` | wait until then |
| Otherwise | allowed |

`record()`:

- When `decide()` would make the attempt wait, returns the state **unchanged**.
- Otherwise starts from zero when there is no state or it has expired, adds one attempt, sets the last attempt to
  `$now`, and, when `$penalise` is true, adds one penalty step to the cumulative wait, capped at `budget × step`.

With a budget of 3, a step of 10 s and a window of 100 s: a first penalised attempt at 0 s makes the next wait
until 10 s; a second penalised attempt at 10 s makes the next wait until 30 s; a third attempt at 30 s spends the
budget, and the key waits until 130 s, one window after that last attempt.

## A payment attempt, end to end

```php
$now = $clock->now();
$keys = [PaymentAttemptKey::forOrder($orderId), PaymentAttemptKey::forClientIp((string) $request->getClientIp())];
$states = array_map($store->find(...), $keys);

$decision = PaymentAttemptDecision::mostRestrictive(...array_map(
    static fn (?PaymentAttemptState $state): PaymentAttemptDecision => $limiter->decide($state, $now),
    $states,
));

if (!$decision->isAllowed()) {
    return new Response('', 429, ['Retry-After' => (string) $decision->retryAfterSeconds($now)]);
}

// The verdict the browser sent, counted and turned into friction by phil/bot-detection-bundle, then dropped.
$friction = $paymentVerdicts->handle(Verdict::fromSubmitted($request->request->get('verdict')));
$penalise = $friction->adds() && !$processorCanForceChallenge;

foreach ($keys as $i => $key) {
    $store->save($key, $limiter->record($states[$i], $now, $penalise), $limiter->timeToLiveSeconds());
}

// Hand the attempt to the processor, with a forced authentication challenge when $friction->adds() and the processor allows it.
```

Save only after an allowed attempt; a waiting attempt changes nothing.

## What the limiter does not do

- It does not detect robots: the verdict comes from the application (a browser-evaluated robot-or-human verdict,
  for instance), and only the boolean decision to penalise reaches the limiter.
- It does not refuse a payment, decide its risk, or talk to a processor.
- It does not store anything itself; see `PaymentAttemptStateStore` in [extension-points.md](extension-points.md).
