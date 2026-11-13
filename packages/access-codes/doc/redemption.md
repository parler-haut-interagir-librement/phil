# Redemption

```php
$redemption = $codes->redeem($typed, 'member-1842', $request->getClientIp() ?? 'unknown');

if ($redemption->isRedeemed()) {
    $this->unlock('member-1842', $redemption->batchId);
}
```

## The steps

1. The redeemer is checked: a stable identifier of the application, 1 to 191 bytes, never an email address — a value
   with `@` is refused by `InvalidConfiguration`, before any attempt is spent.
2. One attempt is spent on the rate limiter, keyed by the third argument: the client's address, an account, a session.
   Once the attempts are spent, the answer is `TooManyAttempts` with `retryAfter`, and nothing is looked up.
3. The code is read by its format; a code it refuses is `Unknown`.
4. The code is hashed under every key, and the store redeems it in one atomic step.
5. When nothing was redeemed, the store tells why.

## The outcomes

| `RedemptionOutcome` | When | `batchId` | Other fields |
|---|---|---|---|
| `Redeemed` | The code was free, and is now the redeemer's | The code's batch | `redeemedAt`: now |
| `Unknown` | Mistyped, failing its check symbol, or never created | `null` | — |
| `AlreadyRedeemed` | Redeemed before, by anyone | The code's batch | `redeemedAt`: then |
| `Expired` | Its batch expired | The code's batch | — |
| `Revoked` | Its batch was revoked | The code's batch | — |
| `TooManyAttempts` | The caller spent its attempts | `null` | `retryAfter` |

Whether the page tells `AlreadyRedeemed`, `Expired` and `Revoked` apart is the application's choice: telling them
apart helps a person who mistook a card, and the rate limiter keeps it from helping a person who tries codes.

## Atomicity

The store marks a code redeemed only where it is neither redeemed, revoked nor expired at that moment, in one
conditional `UPDATE` of the code's row. The database locks the row: of two redemptions at the same instant, one
changes it and the other finds it redeemed. The Integration suite starts eight processes that redeem the same code at
the same instant on MySQL 8.4 and on PostgreSQL 17: one wins, seven are refused.

## Attempts

`AccessCodes` takes any `Symfony\Component\RateLimiter\RateLimiterFactoryInterface`. A sliding window of ten attempts
per quarter hour and per client address suits a public page; an account's identifier suits a page behind a login.
Every attempt counts, the successful ones too, so that a valid code does not reset the window.
