# Installation

```sh
composer require phil/content-access
```

The package needs PHP 8.5 or later. It requires `phil/rules` for the rules of restricted contents, `psr/clock` for the
instant of a decision and `symfony/http-foundation` for the comparison of network ranges. A clock comes from
`symfony/clock` or any implementation of `Psr\Clock\ClockInterface`.

## A first decision

```php
use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\ContentAccess\Entitlement\InMemoryEntitlements;
use Phil\ContentAccess\Viewer;
use Symfony\Component\Clock\NativeClock;

$entitlements = new InMemoryEntitlements()
    ->with('member-42', new Grant('tier:gold', new DateTimeImmutable('2026-11-01T00:00:00+00:00')));

$decider = new AccessDecider($entitlements, new NativeClock());

$decider->decide(AccessPolicy::members(), Viewer::anonymous())->access;         // Access::Locked
$decider->decide(AccessPolicy::members(), Viewer::member('member-42'))->access; // Access::Full
```

An application gives the decider its own implementation of `Entitlement\Entitlements`, which reads the grants of a
person from its subscriptions, memberships or enrolments: see [extension points](extension-points.md).
