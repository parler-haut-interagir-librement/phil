# Swap board

A volunteer who holds a place and cannot come offers it on the swap board, with a note of 500 characters at most.
Another volunteer takes it. The place passes at once, or, when the shift has `swapApproval`, once a coordinator
approves. An offer is immutable: each step returns the next one.

```php
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;

$offer = SwapOffer::offer($id, $roster, 'ada', 'Sick, sorry', $now);       // Open
$offer = $offer->take($roster, 'cy', $rolesOfCy, $now);                     // Completed, or Pending

if (SwapStatus::Pending === $offer->status) {
    $offer = $offer->approve($roster, 'grace', $now);                       // Completed
}

$roster = $offer->apply($roster, $now);                                     // the place is cy's
```

## The statuses

| Status | Means | Next |
|---|---|---|
| `Open` | on the board, for any volunteer | `take()`, `cancel()` |
| `Pending` | taken, waiting for a coordinator | `approve()`, `refuse()`, `cancel()` |
| `Completed` | the place passes — `apply()` gives the roster | — |
| `Cancelled` | withdrawn by the volunteer who offered it | — |

A coordinator who refuses puts the offer back on the board: it is open again, with the coordinator and the instant in
`decidedBy` and `decidedAt`.

## Who may do what

- **Offer**: a volunteer who holds a place of an open shift.
- **Take**: another volunteer, with the role the shift requires, who holds no place on it — one of the reserve or of
  the interested may.
- **Approve** and **refuse**: a coordinator; the library takes their identifier, and the application decides who is
  one.
- **Cancel**: the volunteer who offered the place, while the offer is open or waiting.

Each step reads the roster: an offer of another shift is refused with `swap.other_shift`, and an offer whose volunteer
no longer holds the place — withdrawn, or taken off by a coordinator — is **stale** and refused with `swap.stale`;
`isStale()` tells a screen which offers to hide. Once the shift is closed, nothing moves: `shift.closed`.

| Reason | When |
|---|---|
| `swap.not_open` | taking or cancelling an offer that is no longer open |
| `swap.not_pending` | approving or refusing an offer that waits for nobody, or applying one not completed |
| `swap.own_offer` | the volunteer who offered the place takes it |
| `swap.not_yours` | another volunteer cancels it |
| `swap.other_shift` | the roster given is another shift's |
| `swap.stale` | the volunteer who offered the place no longer holds it |

The conflicts of the volunteer who takes a place are the application's to check, with `Conflicts`, as for a sign-up.
