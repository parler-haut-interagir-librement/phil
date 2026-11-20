# Features

`FeatureAccess` tells what a subscriber may use: the features of every offer they hold a subscription granting
access to — trialing, active, or past due while its retries last —, and those of the subscriptions of the
organizations they belong to ([organizations.md](organizations.md)). A subscription past due gives what its offer's
dunning stages keep at that many days past due: every feature by default, some, or none ([dunning.md](dunning.md)).

```php
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;

$access->features($accountId);         // ["projects:unlimited", "export"]
$access->grants($accountId, 'export'); // true
```

- Each feature appears once, in the order the offers list them, the subscriber's own first.
- A pending or ended subscription grants nothing.
- Feature names are the application's: the module only carries them from the offer to the subscriber. A
  feature flag that depends on a tier reads `grants()`.
- **A subscriber's features are read once** and kept: a page that checks five features reads the subscriptions
  once with those of their organizations (`SubscriptionRepository::forSubscribers()`), and their offers in one more
  query (`OfferRepository::findByCodes()`), however many subscriptions the subscriber holds. `Subscriptions`,
  `Gifts` and `Seats` forget a subscriber whose subscription or seat they change — subscribing, a payment, a failure,
  a cancellation, the sweep, a redeemed gift, a seat —, forgetting an organization forgets the members read through
  it, `forget(subscriber)` does it for a change made elsewhere, and the service is tagged `kernel.reset`, so a
  long-running worker reads them again for each request or message.
