# Syndication

Two applications, each with its own database, each with this module: one **supplier**, which holds the stock and
ships, and any number of **resellers**, which sell the supplier's products as their own. An application can be both,
a supplier to some partners and a reseller of others.

## Partners

A partner is another application: its code — the `identity` it configured, which messages sent to it are addressed
to —, a name, its role, the https endpoint it receives messages at, and the `whpk_` public key its messages are
verified with.

```php
$partners->register('tea-house', 'The tea house', PartnerRole::Supplier, 'https://tea.example/syndication/shop', 'whpk_…');
$partners->register('corner-shop', 'The corner shop', PartnerRole::Reseller, 'https://corner.example/syndication/shop', 'whpk_…');
$partners->feed('corner-shop', ['TEA-1', 'TEA-2'], 300_000);
```

A reseller is fed a list of SKUs and keeps a share of their retail price, in parts per million: at 300 000, a tea
sold 10.00 costs the reseller 7.00. A partner can move its endpoint or rotate its key (`reach()`), and be suspended:
a suspended partner is sent nothing and heard from no more.

## The feed

On the supplier, each change of a product — its price, its stock, its withdrawal — is published:

```php
$publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 42, true, $clock->now());
```

One `product.updated` message is queued for each active reseller fed that SKU, with the retail price and that
reseller's cost. The application calls the publisher from wherever its products change — a stock movement, a price
book, a product edit. The supplier also keeps each price it published to each reseller, once per change — a stock
movement alone adds nothing —, to check the orders that reseller forwards.

A publication costs the same few queries whatever the number of resellers and of SKUs they are fed: the resellers fed
the SKU are found through the index `phil_commerce_syndication_fed_sku` (`PartnerRepository::resellersFed()`), and
the last price each was sent is read for all of them at once (`PublishedPriceRepository::latestPrices()`).

On the reseller, the message becomes a **syndicated product**, kept per supplier and SKU. A message older than the
one applied changes nothing, since messages may arrive out of order.

## Selling a syndicated product

`SyndicatedSellables` answers the core for the sellable type `syndicated`, whose identifier is `supplier:sku`:

```php
$orderModifier->add($order, SyndicatedSellables::reference('tea-house', 'TEA-1'), 2);
```

The line is priced at the feed's retail price, then by every resolver and processor of the core like any other line:
promotions, wholesale prices, taxes. A product withdrawn or out of stock at the supplier is still known, but sold
through no channel: the core refuses to add it.

## Forwarding an order

Once the reseller's order is placed and paid, its syndicated lines go to their suppliers:

```php
$forwarder->forward($order, ['name' => 'Ada Lovelace', 'line1' => '12 rue Principale', 'city' => 'Montréal', 'country' => 'CA'], $clock->now());
```

One `order.forwarded` message per supplier carries the order number, each line's SKU, quantity and unit cost, and
where to ship, in whatever shape the partners agreed on. Each forward writes in the ledger what the reseller owes the
supplier — the cost of each unit at the last feed. **Forwarding twice changes nothing**: an order already in the
ledger with a supplier is not sent again.

On the supplier, **the reseller's figure is not taken on trust**. Each line's `unit_cost` must be a cost this supplier
published to that reseller for that SKU and currency, in effect at some moment of the last `cost_grace_days` (7 by
default): the price of now, or one replaced within the window, which an order placed just before the change still
carries. A line that matches none refuses the whole message — `400`, as unreadable —, and nothing is recorded or handed
on. Every line is read and checked first — a SKU the reseller is not fed, a quantity or a cost out of bounds —, then the
published prices of all its SKUs are read in one query, and the costs compared. The supplier then records what the
reseller owes at its own published cost, and hands a `ForwardedOrder` to every `ForwardedOrderHandler` service — the
application's own, which prepares the shipment:

```php
final readonly class ShipForwardedOrders implements ForwardedOrderHandler
{
    public function handle(ForwardedOrder $order): void
    {
        // $order->reseller, $order->orderNumber, $order->lines, $order->shipTo, $order->owed
    }
}
```

A reseller may order only the SKUs it is fed. `ship_to` reaches the handlers and is not stored by the module on the
supplier; on the reseller, it stays in the outgoing message only until the message is delivered or given up.

## Tracking the parcels

Once the supplier ships a forwarded order, it tells the reseller, one `shipment.sent` message per parcel:

```php
$tracking->notify('corner-shop', 'R-2026-0412', 'SH-881', ['OOLONG-100G' => 2], $clock->now(), 'Canada Post', 'CP123456789CA');
```

The message carries the order number, the parcel's id at the supplier, its SKUs and units, the carrier and the
tracking number — never where it went. On the reseller, the `Inbox` records the parcel and hands a `PartnerShipment` to
every `PartnerShipmentHandler` service, which marks the order shipped or tells the buyer:

```php
final readonly class TellTheBuyer implements PartnerShipmentHandler
{
    public function shipped(PartnerShipment $shipment): void
    {
        // $shipment->partner, $shipment->orderNumber, $shipment->lines, $shipment->carrier, $shipment->tracking
    }
}
```

- **Only an order in the ledger with the partner is tracked**, and only for the SKUs and units it holds: a parcel of
  another order, or of more units, is refused on both sides.
- A parcel is known by its id at the supplier: notifying it again returns `false` and sends nothing; receiving it
  again changes nothing.
- `ShipmentTracking::shipmentsOf(partner, orderNumber)` lists the parcels of an order, on either side.

## Returns

A buyer sends goods back to the reseller, which sends them on to the supplier of the order:

```php
// On the reseller.
$returns->request('tea-house', 'R-2026-0412', 'RET-31', ['OOLONG-100G' => 1], $clock->now(), 'Canada Post', 'CP987654321CA');

// On the supplier, once the parcel arrived: every unit announced, or those accepted.
$returns->receive('corner-shop', 'RET-31', ['OOLONG-100G' => 1], $clock->now());
```

1. `request()` refuses more units of a SKU than the order holds less what its earlier returns announced, and queues
   `return.requested`. The supplier records the return, checks the same bound against its own ledger, and hands it to
   the `PartnerReturnHandler` services (`requested()`), which expect the parcel.
2. `receive()` takes the units accepted — never more than announced —, **credits them at the unit costs of the
   order's entry in its own ledger**, and queues `return.received` with the credit.
3. The reseller computes the credit from its own ledger: a credit that differs is refused — `400` —, and nothing is
   recorded. Otherwise it records the credit, marks the return received, and calls `received()` on the handlers, which
   refund the buyer.

The module does not refund the buyer and does not touch stock: the handlers do, with the core's refunds and the
application's logistics.

## The ledger

Each forwarded order is one entry per partner: negative on the reseller, which owes the supplier; positive on the
supplier, which is owed. The entry keeps the order's lines — each SKU with its quantity and unit cost —, which
tracking and returns read. A return received is an entry of its own under the return's number, of the opposite sign.
The partners settle the way they agreed — a monthly transfer, a credit card on file —, and each records it:

```php
$ledger->balance('tea-house', Currency::of('CAD'));          // −1 260.00: owed to the tea house
$ledger->settle('tea-house', Currency::of('CAD'), $clock->now());
```

## Receiving and delivering

The controller hands each request to the `Inbox`, which verifies the signature with the partner's key, checks the
timestamp against the tolerance and the audience against `identity`, ignores a message id already received, and
applies the message. A message id is the sender's own: it is remembered per partner, so another partner using the
same id is not mistaken for it. The controller answers `204` for a message applied or already received, `404` for an
unknown partner, and `400` for anything refused — badly signed, stale, addressed elsewhere, unreadable, an amount out
of range, a cost never published.

The `Courier` delivers the outbox: each due message is signed with this application's key and posted.

| Answer | What the courier does |
|---|---|
| `2xx` | The message is delivered |
| A `4xx` other than `404`, `408`, `425` and `429` | The partner refused it, and would refuse the same message again: it is given up at once, its error kept |
| None, `404`, `408`, `425`, `429`, `5xx` or anything else | The next attempt comes after 1, 2, 4, 8… minutes, until `max_attempts`; the partner's other messages wait for the next run |

Every attempt carries the same message id, so the partner applies it once. A refusal for a stale timestamp means the
two clocks disagree: keep both synchronised. Each message is claimed for 30 minutes before it is sent, in one atomic
update: two runs at once — one slower than the scheduler's minute — never send the same message, and a run that dies
leaves its claims to lapse.

## Retention

A message's work ends when it is delivered or given up. At that moment, its personal data — `ship_to`, the address of
a forwarded order — is cleared from the stored message. The messages of parcels and returns carry none: SKUs,
quantities, amounts, carriers and tracking numbers only. `phil:commerce-syndication:purge` deletes the outgoing
messages that ended, and the ids of the messages received, more than `retention_days` ago (90 by default): schedule
it once a day. A message still waiting for an attempt is never purged. Once a received id is purged, a partner
resending that very message would have it applied again: keep `retention_days` well above the longest retry of the
partners, which `max_attempts` bounds.
