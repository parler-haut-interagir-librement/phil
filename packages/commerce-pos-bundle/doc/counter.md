# At the counter

What the counter adds to a sale: sale presets, the customer display, draws entered by hand, tickets, and the
cash-difference report.

## Sale presets

A preset is a button at the register over the core's channels: a convention, a member, a wholesale buyer, a pickup.
A cart sold under one goes through its channel — the register's when it names none — to its customer group, every
line priced again by the core, and may need the customer identified before it is rung up.

```php
$presets->create('member', 'Member', channel: null, customerGroup: 'members', identifiesCustomer: true);
$preset = $presets->apply($order, 'member', 'front', $clock->now());
$order->assignCustomer($customerId);
$checkout->ring($order, $session, 'cashier-7', $clock->now(), $preset);
```

Ringing up a cart under a preset that identifies the customer, without one, throws `PosRefused`. The sale keeps the
preset's code, in the `preset` column of `phil_commerce_pos_sale`. `SalePreset::retire()` takes a preset off the
buttons; `active()` lists the others, by code.

## The customer display

The screen turned to the customer — a second tablet opened by a QR code, fed by Mercure — is the application's;
`CustomerDisplay::show($order)` gives what it shows:

```php
$cart = $customerDisplay->show($order);
$mercure->publish(new Update('pos/front/display', json_encode($cart->toArray())));
```

`DisplayedCart` holds the lines — description, quantity, unit price, subtotal —, the items, the discounts, the taxes
(included or added), the tip, the total, what is paid and what is left, amounts in minor units with their currency in
`toArray()`. **It shows nothing about the person**: no name, no balance of points, no customer number.

## Draws entered at the counter

A loot box can be opened in front of the customer with real dice. The package that provides the draw provides a form
for it — an implementation of `Application\Contract\TillDrawForm`, tagged `phil_commerce_pos.till_draw_form` by
autoconfiguration —, and the register shows it once the order is paid:

```php
foreach ($tillDraws->pending($order) as $draw) {
    // {{ include(draw.template, {draw: draw, unit: 1}) }} for each of the draw.units
}

$tillDraws->record($order, $draw->reference, $draw->option, [['dice' => '3,4'], ['dice' => '6,6']]);
```

| Method of `TillDrawForm` | Contract |
|---|---|
| `supports(string $drawer): bool` | Whether it enters the draws of the drawer an option names |
| `template(string $drawer): string` | The Twig template of the form for one unit, rendered with `draw` (`PendingDraw`) and `unit` |
| `read(string $drawer, array $entered, OrderLine $line, string $option, int $unit): string` | The draw as a receipt shows it, from what the cashier entered; `InvalidValue` when it is not a draw the drawer can give |

`record()` refuses an order that is not paid, an option with nothing left to draw, and a count of entries other than
the line's quantity, and records the draws as the core's — which the receipt then shows. An option no form enters is
left to the core's `OptionDraws`, which draws it by its drawer.

## Tickets

A ticket of a box office is a sellable of the core like any other: added to the cart with the core's
`OrderModifier::add()` under its reference type, rung up and paid at the register as a product is.

## The cash-difference report

`CashDifferences::between($from, $to, $register)` lists the sessions closed from the first moment included to the
second excluded — every register's, or one's —, each with what its count found over or short, and the totals over
and short by currency. A session whose difference is beyond `cash_difference_threshold` minor units is flagged:

```php
$report = $cashDifferences->between($monday, $nextMonday);
$report->flagged();   // the sessions someone should look at
```
