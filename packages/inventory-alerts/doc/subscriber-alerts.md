# Subscriber alerts

## Subscribing

A subscription is made only by a customer's explicit request — a form they fill in with their address —, never from
an account, an order or a newsletter list.

```php
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Subscription\Price;

$email = EmailAddress::parse($form['email']);

$subscriptions->subscribeBackInStock('MUG-BLUE', $email, $now);
$subscriptions->subscribePriceDrop('MUG-BLUE', $email, new Price(1_999, 'CAD'), $now); // at 19.99 CAD or less
```

An address has at most one active subscription per SKU and kind. Subscribing again returns the back-in-stock
subscription it has; a price-drop subscription keeps its place and takes the new threshold. A `Price` is in the minor
units of an ISO 4217 currency, and a price is at or below a threshold only in the same currency.

## Waves

When units are received, the application calls `SubscriberAlerts::restocked()` with the units available. The wave
holds the first active subscribers in the order they subscribed, as many as the `WavePolicy` gives for those units:

```text
wave = min(maximum, max(minimum, units × subscribers per unit))
```

With the default policy — 2 subscribers per unit, 10 at least, 500 at most —, 3 units tell 10 people and 1,000 units
tell 500. The application calls again later, once the first buyers have had their chance, while units remain and the
last wave was `full`; those already told are not told twice. No unit available tells nobody.

When a price changes, `SubscriberAlerts::priceChanged()` tells the subscribers whose threshold the new price is at or
below, up to the maximum of a wave; the application calls again at once while the wave was `full`.

```php
$wave = $alerts->restocked('MUG-BLUE', available: 3, at: $now);

if (null !== $wave && $wave->full) {
    // Schedule the next wave in an hour if units remain.
}
```

The library checks again what the store returns — active, same SKU and kind, threshold reached — and sorts it again
by subscription date then identifier.

## After the alert

`SubscriberNotifier` sends the wave; then its subscriptions are marked notified, and are no longer active. If the
notifier throws, nothing is marked and the wave is sent again on the next call. A subscription serves one alert: once
it is delivered, `Subscriptions::purgeNotified($before)` deletes it, address included.

A wave carries the kind, the SKU, the subscriptions and, for a price drop, the new price — never the units left. An
alert says the SKU is back or cheaper; it never presses with a scarcity.

## One-click unsubscription

Every alert carries an unsubscription link with the token of its subscription:

```php
$token = $subscriptions->unsubscribeToken($subscription);
// https://shop.example/alerts/unsubscribe/{token}, also in the List-Unsubscribe header of the message.

$subscriptions->unsubscribe($token); // deletes the subscription; true for a token this application issued
```

The token is the subscription identifier signed by a keyed SHA-256 hash with the secret given to `UnsubscribeTokens`:
nothing more is stored, and a token cannot be made for another subscription. A link followed twice finds nothing to
delete and still answers true. The page that follows a `GET` link shows a button that posts the token, so that a mail
scanner opening links does not unsubscribe anyone; RFC 8058 one-click posts go to the same action.
