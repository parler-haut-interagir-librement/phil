# Offline sales

The main register — the tablet at the counter — keeps selling when it loses its connection; a mobile register waits
for the network instead ([devices.md](devices.md)). The main register's screen gives each sale an id, and the
module's Stimulus controller keeps the sales in the browser's IndexedDB until the server takes them.

## In the browser

```html
<div data-controller="phil--commerce-pos-bundle--offline-queue"
     data-phil--commerce-pos-bundle--offline-queue-url-value="/register/offline-sales"
     data-action="pos:sale->phil--commerce-pos-bundle--offline-queue#sell">
    Waiting: <span data-phil--commerce-pos-bundle--offline-queue-target="count">0</span>
</div>
```

The register's own script dispatches `pos:sale` with the sale in `detail.sale`, its id from `newSaleId()` of
`offline_queue.js`. The controller queues it in an IndexedDB database of its own — named by its `database` value,
`phil-commerce-pos-offline-sales` by default —, then sends the queue in order; it sends again when the page connects
and whenever the browser comes back online. The database survives a reload, a closed tab and a restarted tablet; a
sale queued twice under its id is kept once.

| Answer | What the queue does |
|---|---|
| 2xx whose JSON body names the sale's id | The sale is done, imported now or before |
| 4xx other than 401, 408 and 429 | The sale is set aside among the rejected, with its status, for someone to look at |
| None, a redirect, 401, 408, 429, 5xx, or a 2xx that does not name the sale | The flush stops; the sale and those after it wait |

The controller never follows a redirect (`redirect: 'manual'`): a session that expired while the register was
offline answers with a redirect to the login page, and the sale waits until the cashier signs in again, instead of
being taken for sent. A sale leaves the queue by its id once answered, so two tabs flushing the same database send a
sale twice at worst — which the server recognises — and never drop one unsent.

After each flush, the controller dispatches `flushed` with `{sent, rejected, left}`.

## On the server

`OfflineSaleController` reads one sale, sent as `application/json` — any other type is refused with `415`, so that a
cross-site form cannot post one without the browser asking first:

```json
{"id": "0192f3c4-…", "session": "0192f3a0-…", "by": "cashier-7", "rung_at": "2026-11-03T14:05:00-05:00",
 "total": 2415, "tip": 200,
 "lines": [{"type": "product", "id": "TEA-1", "quantity": 2}],
 "payments": [{"method": "cash", "amount": 1415}, {"method": "debit", "amount": 1000, "approval": "A1B2C3"}]}
```

`OfflineSales` imports it only into a session of a main register (`409` otherwise), and never over an order that
exists already: a sale held on a phone is picked up at the counter with the network, never offline. It turns the
sale into an order numbered by its id, rung up in its session, **re-priced by the server** — the
prices, promotions and taxes of now —, and takes its payments up to what the order comes to. The register already
gave the change: the cash taken is the cash due, rounded. **A difference is never charged nor refunded silently**:
the imported sale keeps it, the server's total less the register's, for someone to review.

`rung_at` is an RFC 3339 moment with seconds and an offset or `Z`, a fraction allowed: what `Date.prototype.toISOString()`
gives, `2026-11-03T19:05:00.000Z`, is read. A date or time that does not exist — the 30th of February — is refused,
not rolled over. `total`, `tip` and each payment's `amount` are 0 to 10^12 minor units, a line's `quantity` 1 to
100000, and every payment is in cash or a method the register records (`recorded_methods`).

| Answer | Meaning |
|---|---|
| `201` | Imported now: `{"id": …, "discrepancy": 0}` |
| `200` | Already imported: the same answer, nothing done |
| `403` | The application's `OfflineSaleGate` refused the sale |
| `409` | The session is unknown or closed — sync before closing the session —, its register is mobile, or the sale's id is an order's number already |
| `415` | The body was not sent as `application/json` |
| `422` | The sale cannot be read, is out of bounds or overflows once re-priced, is paid by a method the register does not take, or sells what is no longer sold |

## Who may send a sale

The sale names its cashier, `by`, and its session, as the register sent them. Routing the controller behind the
application's access control decides who may post at all; **binding the sale to the signed-in user is the
application's**, through `Application\Contract\OfflineSaleGate`, which `OfflineSales` receives by autowiring:

```php
final class SignedInCashier implements OfflineSaleGate
{
    public function allows(OfflineSale $sale, CashSession $session): bool
    {
        $user = $this->security->getUser();

        return null !== $user
            && $sale->by === $user->getUserIdentifier()
            && $this->rosters->works($user, $session->register);
    }
}
```

Without a gate, the module takes `by` and `session` as sent: any user allowed to post could import a sale into
another register's session, in another cashier's name. Keep Symfony's `SameSite=Lax` session cookie, and add a CSRF
header of the application's own if its cookies are `SameSite=None`.
