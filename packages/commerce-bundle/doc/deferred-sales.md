# Deferred sales

A deferred sale is a placed order paid later: a deposit first, then a balance called by a trigger. Preorders,
installments and layaways are three settings of one policy, not three mechanisms. **An order has at most one deferred
sale**, so one deferred due date.

| Type | Role |
|---|---|
| `Domain\ValueObject\DeferredSalePolicy` | Deposit, balance trigger, stock commitment, fulfilment, days to the due date; `preorder()`, `installments()`, `layaway()` |
| `Domain\ValueObject\Deposit` | What is asked first: `none()`, `share()` in basis points, `fixed()` |
| `Domain\Deferred\BalanceTrigger` | `Date`, `Shipment`, `Availability`, `Manual` |
| `Domain\Deferred\StockCommitment` | `OnOrder`, `OnBalance` |
| `Domain\Deferred\Fulfilment` | `OnDeposit`, `OnBalance` |
| `Domain\Deferred\DeferredSaleState` | `AwaitingDeposit`, `AwaitingBalance`, `BalanceDue`, `Settled`, `Cancelled` |
| `Domain\Model\DeferredSale` | The sale of one order, keyed by its number, on `phil_commerce_deferred_sale` |
| `Domain\Event\DeferredSaleEvent` and its seven subclasses | What happened to a sale, by its order's number |
| `Domain\Contract\DeferredSaleRepository` | Finds and stores sales, lists the balances due; `DoctrineDeferredSaleRepository` with DoctrineBundle |
| `Domain\Exception\DeferredSaleRefused` | A sale that cannot be opened, called or cancelled |
| `Application\Contract\StockCommitments` | The port that holds and frees the stock ([extension-points.md](extension-points.md#stockcommitments)) |
| `Application\DeferredSales` | Opens, follows, calls, cancels; what is due, whether the goods may leave |

## The policy

| Setting | Values | Meaning |
|---|---|---|
| Deposit | `Deposit::none()`, `share(2_500)`, `fixed($money)` | Nothing, a share of the total in basis points, or a fixed amount capped at the total |
| Trigger | `Date`, `Shipment`, `Availability`, `Manual` | What calls the balance once the deposit is paid |
| Stock commitment | `OnOrder`, `OnBalance` | The stock is held when the sale opens, or once the balance is paid |
| Fulfilment | `OnDeposit`, `OnBalance` | The goods may leave once the deposit is paid, or once the balance is paid |
| Days to the due date | 1 to 3,650, `Date` only | The due date counted from the opening, unless one is given then |

| Named constructor | Trigger | Stock | Goods leave |
|---|---|---|---|
| `preorder($deposit)` | `Availability` by default; `Shipment`, `Date` or `Manual` | `OnBalance` by default, or `OnOrder` | On the balance |
| `installments($deposit, $days)` | `Date` | `OnOrder` | On the deposit |
| `layaway($deposit, $days)` | `Date` | `OnOrder` | On the balance |

Any other combination is built with the constructor. A share is rounded as `Money::allocate()` shares the total; a
deposit is kept as computed when the sale opens.

## States

```text
awaiting deposit → awaiting balance → balance due → settled
awaiting deposit, awaiting balance → settled (paid in full early)
awaiting deposit, awaiting balance, balance due → cancelled
```

- **The sale follows the order's payments**, which `DeferredSales::recordPayments()` reads through `Payments`: the
  deposit is received once what was captured, less what went back, reaches it, and the sale is settled once that
  reaches the order's total. A deposit of nothing is received when the sale opens.
- The balance is called only once the deposit is paid, and only by the sale's own trigger; a date calls it only once
  the due date is reached.
- Paying more than is asked is never refused: the sale settles early.
- A settled or cancelled sale moves no more.

## Example

```php
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;

// A layaway: a fifth now, the rest within 60 days; the goods are put aside at once.
$sale = $deferredSales->open($order, DeferredSalePolicy::layaway(Deposit::share(2_000), 60), $clock->now());
$payments->start($order, 'card', $clock->now(), $deferredSales->amountDue($order));

// After each payment notification or refund:
$deferredSales->recordPayments($order, $clock->now());

// A scheduled task calls the balances whose due date is reached, 100 at a time:
$deferredSales->callDue($clock->now());

// A preorder: the logistics module or the catalogue says the goods arrived.
$deferredSales->callBalance($order->number, BalanceTrigger::Availability, $clock->now());

$deferredSales->mayFulfil($order->number); // whether the goods may leave
```

`callBalance()` returns `false`, and changes nothing, for an order without a deferred sale waiting for that trigger:
whoever sees a trigger calls it for every order it concerns. `callDue()` takes 1 to 1,000 sales at a time, the
earliest due first; a scheduled task calls it again until it returns fewer than its limit.

## Events and the stock

Each change gives its events, in the order they happened:

| Event | When |
|---|---|
| `DeferredSaleOpened` | The sale opens |
| `DepositReceived` | The payments reach the deposit |
| `BalanceCalled` | The trigger came |
| `DeferredSaleSettled` | The payments reach the total |
| `DeferredSaleCancelled` | The sale is given up |
| `StockCommitted` | The stock is held: when the sale opens with `OnOrder`, once settled with `OnBalance` |
| `StockReleased` | A cancelled sale frees the stock it held |

`DeferredSales` first calls every `StockCommitments` service for `StockCommitted` and `StockReleased`, then
dispatches every event through Symfony's event dispatcher, by its class. An event carries the order's number and the
moment; a listener reads what else it needs from its own store. A module listens without the core knowing it:

```php
use Phil\CommerceBundle\Domain\Event\BalanceCalled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AskForTheBalance
{
    public function __invoke(BalanceCalled $event): void
    {
        // send the buyer the link to pay what is due
    }
}
```

The events are dispatched before the unit of work is flushed, which the application does, as it does for orders and
payments; a listener that sends something to the outside world does it after the commit, through Messenger for
instance.

## Cancelling

`DeferredSales::cancel()` gives the sale up and releases the stock it held. **What was paid, and the order itself, are
the application's**: it refunds through `Payments::refund()`, keeping what the shop's terms allow, and cancels the
order. A layaway whose due date passed without payment is the application's to cancel; the bundle never cancels on its
own.

## Storage

One row per sale in `phil_commerce_deferred_sale`, keyed by `order_number`, with a version column; the deposit in minor
units with its currency; `balance_trigger`, `stock_commitment`, `fulfilment` and `state` as their enums' values;
`stock_committed`; `opened_at`, `due_at`, `deposit_paid_at`, `balance_called_at`, `closed_at`; an index on `state` and
`due_at`, which `dueForBalance()` reads through DBAL, order numbers only.
