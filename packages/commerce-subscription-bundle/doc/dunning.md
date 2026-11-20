# Dunning

While a renewal is past due — its payment failed and is being retried —, the offer's dunning stages say what the
subscriber keeps, from so many days past due on: every feature, some of them, or none. An offer without stages keeps
every feature while the retries last, so a store that sets nothing never cuts anyone off by surprise.

```php
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DunningStage;

$offer->changeDunning([
    DunningStage::full(0),                     // the first days, everything
    DunningStage::reduced(3, ['read']),        // from the third day past due, reading only
    DunningStage::cut(10),                     // from the tenth day, nothing
]);
```

| Stage | Keeps |
|---|---|
| `DunningStage::full($days)` | Every feature of the tier |
| `DunningStage::reduced($days, $features)` | The features named, one or more, each a feature of the tier |
| `DunningStage::cut($days)` | No feature |

- **Days are whole days since the first failure** of the renewal, 0 to 365; each stage starts later than the one
  before, at most ten stages per offer. Before the first stage, every feature is kept.
- **The first failure starts the count**: `Subscription::pastDueSince()` is the moment of the first failed payment of
  the renewal, and `daysPastDue($at)` the whole days since; a period paid clears it, and the next failure starts a new
  count.
- **The retries and the end are unchanged**: the subscription stays past due until a payment succeeds or the last
  attempt of `payment_attempts` fails, then ends; the stages only decide what `FeatureAccess` grants meanwhile.
- **`FeatureAccess` reads the moment from the clock** — the container's `clock` service when there is one, the system
  time otherwise — and keeps a subscriber's features until it is reset, as for every other change
  ([features.md](features.md)); a long-running worker resets it for each request or message.
- **An organization's subscription past due** gives its members what its stages keep, the same way.

The stages are stored with the offer, in the JSON column `dunning`; the moment of the first failure in the column
`past_due_since` of the subscription.
