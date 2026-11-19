# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application implements, configures or relies on. The
ports of the core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag |
|---|---|---|
| `PaymentGateway` | `RegisterGateway`, for `cash` and the recorded methods | `phil_commerce.payment_gateway` |
| `PaymentGateway` | `TerminalGateway`, for the connected terminals' method | `phil_commerce.payment_gateway` |

## `PaymentTerminal`

`Phil\CommercePosBundle\Application\Contract\PaymentTerminal` — one adapter per processor of connected terminals,
tagged `phil_commerce_pos.payment_terminal` by autoconfiguration and found by its `name()`. Its contract, and how to
write one, are in [terminals.md](terminals.md#writing-an-adapter). The module ships `FakePaymentTerminal`, registered
with `terminals.fake: true`.

## `TillDrawForm`

`Phil\CommercePosBundle\Application\Contract\TillDrawForm` — the form that enters at the counter the draws of a drawer,
real dice rolled in front of the customer; the package that provides the drawer provides it, tagged
`phil_commerce_pos.till_draw_form` by autoconfiguration. See [counter.md](counter.md#draws-entered-at-the-counter).

## `OfflineSaleGate`

`Phil\CommercePosBundle\Application\Contract\OfflineSaleGate` — whether whoever sends an offline sale may import it.
Registered under the interface, it is autowired into `OfflineSales`; without it, a sale is taken as sent. See
[Offline sales](offline.md#who-may-send-a-sale).

| Method | Contract |
|---|---|
| `allows(OfflineSale $sale, CashSession $session): bool` | Whether the signed-in user may send sales rung up by the sale's cashier (`by`) in that session; asked before anything is read or written, a refusal being `OfflineSaleForbidden` |

## Repositories

`RegisterRepository`, `CashSessionRepository` and `RegisterSaleRepository` are public aliases of
`DoctrinePosRepository`; an application points them at a service of its own under the same contracts.

## Configuration

| Option | What it extends |
|---|---|
| `recorded_methods` | The methods the register records without processing them: a terminal that is not connected, a cheque |
| `tip_label` | How a tip reads on the receipt |
| `layaway_deposit` | The share of an order paid before it is put aside, in parts per million |
| `cash_difference_threshold` | The cash over or short, in minor units, beyond which the cash-difference report flags a session |
| `terminals.method` | The core's payment method of the connected terminals, `card_present` by default |
| `terminals.fake` | Registers the fake terminal adapter, for tests and development |
| `cashier_pin.enabled`, `cashier_pin.limiter` | The cashier switch by PIN, and the service id of its rate limiter factory |

## Services and the browser

| Point | How |
|---|---|
| `Registers`, `CashSessions`, `RegisterCheckout`, `RegisterPayments`, `Layaways`, `OfflineSales` | The register in code; public — see [The register](register.md) and [Layaways](layaways.md) |
| `TerminalPayments`, `SalePresets`, `CustomerDisplay`, `TillDraws`, `CashDifferences`, `CashierSwitch` | Public — see [terminals.md](terminals.md), [counter.md](counter.md) and [devices.md](devices.md) |
| `OfflineSaleController`, `TerminalNotificationController` | Routed by the application ([installation.md](installation.md#routing)) |
| The offline queue | The register's own script dispatches `pos:sale` with the sale; the controller `offline-queue` keeps it in the IndexedDB database its `database` value names, sends it to its `url` value, and dispatches `flushed` with the counts |
