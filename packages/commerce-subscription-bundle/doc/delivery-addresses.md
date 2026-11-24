# Delivery addresses

A subscription that ships something, a printed issue, a welcome gift or a reward of a tier, keeps where its
shipments go. Each order of the subscription freezes a copy of that address. Both are erased some days after the last
shipment. The address is a `Phil\Address\Address` from `phil/address`, checked and written the way its country writes
it.

`Application\DeliveryAddresses` holds the whole of it. Nothing here asks for an address: the application asks for one
only for the tiers that ship, and leaves it out of every other subscription.

## The address of a subscription

```php
use Phil\Address\Address;

$addresses->change($subscription, Address::of('CA', [
    'givenName' => 'Ada',
    'familyName' => 'Lovelace',
    'addressLine1' => '1 rue Saint-Paul',
    'locality' => 'Montréal',
    'administrativeArea' => 'QC',
    'postalCode' => 'H2Y 1G6',
]), 'post');

$entityManager->flush();
```

- **`change()`** keeps the address, the code of the delivery method that carries the shipments, and a telephone
  number when the method's carrier asks for one. It returns the `DeliveryAddress` kept.
- **`remove()`** forgets it, with the moment of the last shipment.
- **`of()`** reads it, or `null`.

Check the address with `AddressValidator` of `phil/address`, or the `ValidAddress` constraint of
`phil/address-bundle` in a form, before handing it here. `change()` checks the method and the telephone number only.

### The telephone number

A telephone number is kept **only when the carrier of the delivery method asks for one**:

- a method listed in `delivery_addresses.phone_required_methods` refuses an address without a number, with
  `SubscriptionRefused::phoneRequired()`;
- any other method keeps no number, even when one is given.

The number is written as E.164, such as `+15145550123`. Read what the subscriber typed with `phil/phone`, and hand
the normalised number.

```yaml
phil_commerce_subscription:
    delivery_addresses:
        phone_required_methods: [courier]
```

An application whose carriers say it otherwise, by country or by weight for instance, declares a service
implementing `Application\Contract\PhoneRequirement`, aliased under that interface.

## The copy frozen in each order

Each order that `Subscriptions` places, the order of a period and the order of a change at once, freezes the
subscription's address, when it has one, in a row of `phil_commerce_subscription_order_address` keyed by the order's
number. The subscriber who moves changes the address of the next orders only: the order already placed keeps the
address it was placed with.

```php
$address = $addresses->ofOrder('2026-0042');   // the DeliveryAddress frozen, or null
```

- **`freeze()`** is what `Subscriptions` calls. Called again for the same order, it returns the copy already frozen
  and changes nothing.
- **`shipped($orderNumber, $at)`** records that the order's shipment left, on the copy and on its subscription. The
  erasure counts from that moment. Call it when the carrier takes the parcel, from the label of
  `phil/commerce-logistics-bundle` for instance.
- **`copiesOf($subscriber)`** lists a subscriber's copies, oldest first.

## Erasure

An address is erased `delivery_addresses.retention_days` after its last shipment, 90 days by default:

| What | Erased when |
|---|---|
| The address of a subscription | The subscription ended, and its end and its last shipment, if any, are both older than the retention |
| The copy in an order | Its shipment left more than the retention ago; without a recorded shipment, it was frozen more than the retention ago |

An active subscription keeps its address, whatever the date of its last shipment, since its next shipments need it.

`purge($at)` erases what has outlived the retention at `$at`, by batches of `delivery_addresses.purge_batch`, until
a batch comes back short. `erase($subscriber)` erases all of a subscriber's addresses and copies. Both write at once,
and both are **idempotent**: run again, or by two workers at the same time, the second run finds nothing left and
answers zeros.

### The command and the schedule

```sh
php bin/console phil:commerce-subscription:purge-addresses
php bin/console phil:commerce-subscription:purge-addresses --limit=100
```

With `symfony/scheduler`, the schedule `phil_commerce_subscription_addresses` sends a `PurgeDeliveryAddresses`
message once a day, which `PurgeDeliveryAddressesHandler` handles. Consume it with:

```sh
php bin/console messenger:consume scheduler_phil_commerce_subscription_addresses
```

The message carries nothing: the handler reads the time from the container's `clock` and the retention from the
configuration.

### Several workers

With `symfony/lock` and the service `lock.factory`, which FrameworkBundle registers when a lock store is configured,
the schedule runs under a lock, so that several workers consuming it send each purge once. The command takes a lock of
its own and leaves a purge already running to finish. The lock store must be shared by every worker, Redis or the
database rather than files on one server.

```yaml
phil_commerce_subscription:
    delivery_addresses:
        lock_factory: my.lock.factory   # another LockFactory; null for none
```

Without a lock, two workers may purge at the same time: the purge stays correct, each row being erased once, and only
the work is done twice.

## Access and erasure requests

With `phil/privacy-bundle` registered, `Infrastructure\Privacy\DeliveryAddressPrivacy` answers its access and erasure
requests under the module `commerce_subscription.delivery_addresses`, the data subject being the subscriber's
identifier:

- the export gives the address of each subscription, with its last shipment, and each copy, with the moments it was
  frozen and shipped;
- the erasure clears the address of each subscription (`Redacted`, the subscription stays) and deletes the copies
  (`Deleted`). Run again, it reports the same effects.

## Storage

The subscription keeps its address in the nullable columns `delivery_address` (JSON) and `last_shipped_at` of
`phil_commerce_subscription`; the copies live in `phil_commerce_subscription_order_address`. Run
`doctrine:migrations:diff` after updating: the migration adds the two nullable columns, so the existing subscriptions
keep working without an address, and creates the table of the copies.

## Reading the address elsewhere

Another package reads the address through `DeliveryAddresses::of()` for a subscription and `ofOrder()` for an order,
behind a port of its own: a list of shipments to prepare, a label, an export for a shipper. It never reads the
columns.
