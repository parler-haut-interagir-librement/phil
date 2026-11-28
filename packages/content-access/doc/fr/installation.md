<!-- phil-dev source-sha256=eb6b0ee1a47747633c98e88eae7f84c3fbb4235c485df64ec71ed97b38d93842 -->
# Installation

```sh
composer require phil/content-access
```

Le paquet exige PHP 8.5 ou une version plus récente. Il requiert `phil/rules` pour les règles des contenus restreints,
`psr/clock` pour l'instant d'une décision et `symfony/http-foundation` pour la comparaison des plages de réseau. Une
horloge vient de `symfony/clock` ou de toute implémentation de `Psr\Clock\ClockInterface`.

## Une première décision

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

Une application donne au décideur sa propre implémentation de `Entitlement\Entitlements`, qui lit les droits d'une
personne dans ses abonnements, ses adhésions ou ses inscriptions : voir les [points d'extension](extension-points.md).
