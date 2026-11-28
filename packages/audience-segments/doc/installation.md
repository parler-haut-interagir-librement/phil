# Installation

```sh
composer require phil/audience-segments
```

The package needs PHP 8.5 or later, phil/rules, psr/clock and psr/event-dispatcher. It ships no Symfony bundle: the
application implements the ports over its own modules and registers the services.

## Wiring

```yaml
# config/services.yaml
services:
    app.audience_conditions:
        class: Phil\Rules\Type\ConditionTypes
        factory: ['Phil\AudienceSegments\AudienceConditions', 'types']
        arguments:
            $participation: '@App\Audience\EventParticipation'
            $purchases: '@App\Audience\ShopPurchases'
            $tags: '@App\Audience\PeopleTags'

    Phil\AudienceSegments\SegmentJson:
        arguments:
            $types: '@app.audience_conditions'

    Phil\AudienceSegments\Segments:
        arguments:
            $audience: '@App\Audience\NewsletterSubscribers'
            $exclusions: '@App\Audience\Suppressions'

    Phil\AudienceSegments\Recalculation:
        arguments:
            $store: '@App\Audience\DoctrineMembershipStore'
```

A condition type is registered only when its port is given: an application without a shop leaves `$purchases` out,
and a segment naming `purchase.made` is then refused when it is read. Symfony's clock and event dispatcher fill the
PSR interfaces.

## Storing segments

An application keeps each segment as its identifier and its definition, the text `SegmentJson::write()` gives, and
reads it back with `SegmentJson::read($id, $definition)`. A frozen list is kept as its segment, name, fingerprint, date
and members, one row per member or one column of identifiers, and built again with `new FrozenList(...)`, which checks
that the members are distinct and sorted.

## Recalculating on a schedule

`Recalculation::recalculate()` walks the whole audience; run it from a scheduled command or message rather than in a
request. With Symfony Scheduler, one recurring message per segment, carrying the segment identifier only, is enough.
