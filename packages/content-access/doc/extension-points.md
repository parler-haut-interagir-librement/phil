# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Give the grants of a person | Implement `Entitlement\Entitlements`; join several with `Entitlement\ChainEntitlements` |
| Let a rule read the application's facts | Pass them as the third argument of `AccessDecider::decide()` |
| Add a condition a rule may name | Implement `Phil\Rules\Type\ConditionType`, and pass the registry to `AccessConditions::types()` |
| Mark the cut of a document with another block type | Pass it to `PreviewCut` |
| Store policies | Use `Json\AccessPolicyReader` and `Json\AccessPolicyWriter` |

Every implementation keeps nothing from one request to the next, so that one instance serves every request of a worker.

## A source of grants

A source answers the grants of a person by their stable identifier, never by an email address. It may return ended
grants: the library keeps those active at the instant of the decision.

```php
use Phil\ContentAccess\Entitlement\Entitlements;
use Phil\ContentAccess\Entitlement\Grant;

final readonly class SubscriptionEntitlements implements Entitlements
{
    public function __construct(private Subscriptions $subscriptions)
    {
    }

    public function grantsOf(string $subject): array
    {
        $grants = [];

        foreach ($this->subscriptions->activeFor($subject) as $subscription) {
            foreach ($subscription->features() as $feature) {
                $grants[] = new Grant($feature, $subscription->startedAt(), $subscription->paidUntil(), 'subscription');
            }
        }

        return $grants;
    }
}
```

A `Grant` holds a feature name and an optional source — identifiers: a letter or digit, then letters, digits, `_`, `.`,
`:`, `/` or `-`, 128 bytes at most —, a start, and an optional end after the start; `InvalidGrant` refuses anything
else.

The decider reads a source only for a signed-in viewer, and only when a condition asks for a feature. A source that
reads a database does so once per decision; an application that decides a list of contents for one viewer wraps its
source to read the grants once for the list.

## The application's facts

```php
$decider->decide($policy, $viewer, [
    'lesson.done' => static fn (): bool => $progress->completed($viewer->id, 'lesson-2'),
]);
```

A rule reads them with the generic comparisons of `phil/rules`. The names of [the library's facts](conditions.md) are
reserved.

## Viewers

`Viewer::anonymous($ip)` and `Viewer::member($id, $roles, $verifiedEmailDomain, $ip)` build the viewer; `InvalidViewer`
refuses an empty identifier, one longer than 255 bytes or holding a control character, more than 100 roles, a role
that is not an identifier, a domain that is not a host name and an address that is not an IP address, without
repeating the value in its message.
