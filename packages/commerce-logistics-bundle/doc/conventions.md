# Conventions

A shop that sells printed shirts at conventions prepares a stock for each one, size by size, and prints the shirts it
sells online on demand. `ConventionStock` keeps the quantities prepared for each convention and decides, for an order
placed online, whether its shirts come from that stock or go to print.

```php
$conventions->schedule('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
$conventions->enter('fall-con', 'SHIRT-M', 20);
$conventions->enter('fall-con', 'SHIRT-L', 10);
$conventions->planned('fall-con');     // ['SHIRT-L' => 10, 'SHIRT-M' => 20]
$conventions->shortfall('fall-con');   // ['SHIRT-L' => 10, 'SHIRT-M' => 14]: 6 medium are on the shelves
```

| Method of `ConventionStock` | Effect |
|---|---|
| `schedule(code, name, startsOn, endsOn)` | Adds a convention, or moves the days of one already known |
| `enter(convention, sku, quantity)` | Sets how many units of a SKU — one size of a shirt — are prepared for it; zero or more |
| `planned(convention)` | The quantities entered, by SKU |
| `shortfall(convention)` | What is left to print: the quantities entered beyond what the open locations have available now |
| `near(at)` | The conventions with a day within the window from that moment, the earliest first |
| `toPrint(order, wanted, at)` | The convention items of an order placed online that go to print, without recording anything |
| `print(orderNumber, printed, at)` | Records them and hands them to the `PrintOnDemand` services, once per order and SKU |
| `routed(orderNumber)` | Whether some of the order's units already went to print |

## The route of an order placed online

`Stock::reserve()` asks `ConventionStock` first. For each convention item of the order — a SKU with a quantity entered
for any convention:

- **while a convention is near** — one of its days falls between now and `window_days` days ahead, 30 by default, both
  days included —, the item goes to print, and the stock stays for the convention;
- **when none is near**, the item is reserved from stock if the open locations have enough of it available, and goes
  to print whole otherwise.

The other lines are reserved as usual, and a shortage on them still refuses the order before anything goes to print.
Once some units went to print, the order's route is decided: reserving it again changes nothing.

An order counts as placed online when it has no channel, or when its channel is one of `online_channels`. An order of
another channel — the till at the convention itself — takes from stock as any order does.

## Printing on demand

`Application\Port\PrintOnDemand` is what the application does with the units sent to print. A service implementing it
is tagged `phil_commerce_logistics.print_on_demand` by autoconfiguration, and is called once per order and SKU, in the
transaction that reserves the order. It queues a message carrying the order number and the SKU, and reads the rest —
the design, the size, the buyer's text — from its own store.

```php
final readonly class LocalPrinter implements PrintOnDemand
{
    public function __construct(private MessageBusInterface $bus) {}

    public function print(string $orderNumber, string $sku, int $quantity): void
    {
        $this->bus->dispatch(new PrintShirts($orderNumber, $sku, $quantity));
    }
}
```

The module does not generate SKUs or designs; it reads the SKUs of the order's lines as they are.

## Configuration

```yaml
phil_commerce_logistics:
    conventions:
        window_days: 30           # 0 to 366
        online_channels: [web]
```

## Storage

| Table | Holds |
|---|---|
| `phil_commerce_logistics_convention` | `code` (primary key), `name`, `starts_on`, `ends_on` (dates, indexed) |
| `phil_commerce_logistics_convention_quantity` | `convention` and `sku` (primary key, indexed by SKU), `quantity` |
| `phil_commerce_logistics_print_job` | `id` (UUID v7), `order_number`, `sku`, `quantity`, `sent_at`; unique by order and SKU |
