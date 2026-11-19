# Limits

## `Limit`

A limit is a whole number from zero, or no limit at all:

```php
use Phil\UsageMeter\Limit;

Limit::of(3);
Limit::of(0);          // nothing allowed: a feature the tier lacks
Limit::unlimited();
Limit::parse('250');   // Limit::of(250)
Limit::parse('unlimited');
Limit::parse('many');  // null
```

`allows($used, $amount)`, `remaining($used)` — never below zero, `null` for no limit —, `isUnlimited()`, `toString()`,
and `orHigher($other)`, the more generous of two limits, no limit beating any number. `parse()` reads `unlimited` or
up to 18 decimal digits, without sign or leading zero.

## Where a subject's limit comes from

`UsageMeter` asks its `LimitProvider` for the subject's limit of a meter; a provider that answers `null` has nothing
to say, and the meter's own limit applies:

```php
use Phil\UsageMeter\LimitProvider;
use Phil\UsageMeter\Meter;

final readonly class PlanLimits implements LimitProvider
{
    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        return $this->plans->of($subject)?->limits[$meter->name] ?? null;
    }
}
```

`LimitChain` asks several providers in turn, the first that names a limit winning — an override an administrator set,
then the subscription, then the meter's own:

```php
use Phil\UsageMeter\LimitChain;

new UsageMeter($meters, $store, $clock, new LimitChain($overrides, $subscriptionLimits));
```

The limit is read at each call, so a subscriber who changes tier gets the new limit at once, on the current window:
an upgrade frees what the new limit allows on top of what is used, a downgrade below the use refuses until the window
ends.

## Limits from subscription features

`Subscription\FeatureLimits` reads limits written in features, `usage:<meter>:<limit>`:

| Feature | Limit |
|---|---|
| `usage:exports:20` | 20 exports per window of the meter `exports` |
| `usage:projects:unlimited` | no limit on `projects` |
| `usage:ai.tokens:0` | no tokens at all |

With `phil/commerce-subscription-bundle`, `fromSubscriptions()` reads the features of every offer the subscriber holds
a subscription granting access to — trialing, active, or past due while its retries last:

```php
use Phil\UsageMeter\Subscription\FeatureLimits;

$limits = FeatureLimits::fromSubscriptions($featureAccess);
```

A subscriber holding several offers — a plan and an add-on — gets the most generous limit they name. One whose
features name none for a meter gets the meter's own limit, the free tier's, so the free tier needs no offer. A feature
after the prefix that is not a limit (`usage:exports:many`) is skipped: an offer's mistake never blocks a subscriber's
action. The prefix is the second argument, `usage:` by default.

Without that bundle, give the constructor any closure that returns a subject's features:

```php
new FeatureLimits(fn (string $subject): array => $this->accounts->get($subject)->features);
```
