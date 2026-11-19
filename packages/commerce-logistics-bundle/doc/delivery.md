# Delivery

A delivery method is a way an order reaches its buyer: shipping by a carrier, local delivery, or pickup at a
location. It serves zones, costs a price, may be free from a threshold, and takes a number of days.

```php
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;

$methods->addMethod(DeliveryMethod::of('post', 'Postes Canada', DeliveryKind::Shipping,
    Money::of(1_200, $cad), freeFrom: Money::of(7_500, $cad), zones: ['QC', 'ON'], minDays: 2, maxDays: 5));
$methods->addMethod(DeliveryMethod::of('pickup-mtl', 'Ramassage au magasin', DeliveryKind::Pickup,
    Money::zero($cad), pickupLocation: 'store-mtl', priority: 10));

$options->for($order);                              // the methods serving the order, by priority
$modifier->chooseDeliveryMethod($order, 'post');    // the core's OrderModifier; the cost follows
```

| Argument of `DeliveryMethod::of()` | Rule |
|---|---|
| `code`, `label` | Up to 64 lowercase letters, digits, `_`, `.` or `-`; a label the receipt shows |
| `kind` | `Shipping`, `LocalDelivery`, `Pickup` |
| `price`, `freeFrom` | Zero or more; the threshold in the same currency |
| `zones` | Named as the order's tax zone; every zone when empty |
| `minDays`, `maxDays` | 0 to 365, the least before the most |
| `pickupLocation` | Required for a pickup, refused otherwise |
| `priority` | The order the methods are offered in |

## What an order pays

`ShippingProcessor` is an order processor of the core, tagged after the catalogue's `PromotionProcessor` and before the
core's `TaxOrderProcessor`. For the method the order chose (`OrderModifier::chooseDeliveryMethod()`), if it still serves
the order's tax zone in its currency, it adds one `Shipping` adjustment labelled as the method: its price, or zero once
the lines, **after their promotions**, reach its threshold. A method withdrawn, of another zone or unknown adds nothing;
the application checks `DeliveryOptions::chosen()` before placing the order.

The core's taxes apply to the lines only: a taxable delivery charge is not taxed yet.

## Storage

With DoctrineBundle, a delivery method is one row of `phil_commerce_logistics_delivery_method`: `code` (primary key),
`label`, `kind`, `price`, `currency`, `free_from`, `zones` (JSON), `min_days`, `max_days`, `pickup_location`,
`priority`, `active`.
