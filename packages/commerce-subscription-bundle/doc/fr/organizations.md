<!-- phil-dev source-sha256=e3e189c657babec41a1b5d96ef05f2db6a82850bf26093190f1f9ea64b443b46 -->
# Organisations

Un abonnement détenu par une organisation ouvre ses fonctionnalités à **chaque membre de l'organisation**, ou, quand
son offre compte des **places**, aux membres qui en détiennent une. Le module ne connaît aucune organisation :
l'application dit à quelles organisations appartient un membre, par un port.

## Dire qui appartient à quoi

```php
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;

final readonly class Memberships implements OrganizationMembership
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function organizationsOf(string $member): array
    {
        return $this->members->organizationIdsOf($member);
    }
}
```

- L'organisation s'abonne comme n'importe quel abonné, sous son identifiant stable :
  `$subscriptions->subscribe($organizationId, 'crew', $clock->now())`.
- `organizationsOf()` retourne les identifiants stables des organisations du membre, tels que leurs abonnements nomment
  leur abonné ; les 100 premières sont lues.
- Un service qui implémente `OrganizationMembership` est celui que reçoit `FeatureAccess`. Sans lui, les
  fonctionnalités d'un abonné sont celles de ses propres abonnements.
- Le module ne dépend d'aucun paquet de locataires ou d'organisations : n'importe quel modèle de l'application convient.

## Ce qu'un membre peut utiliser

`FeatureAccess::features($member)` donne d'abord les fonctionnalités des propres abonnements du membre, puis celles
des abonnements de ses organisations qui donnent accès. Tout se lit en une requête pour les abonnements, une pour les
offres, et une pour chaque place à vérifier.

Quand un membre rejoint ou quitte une organisation, l'application appelle `FeatureAccess::forget($member)`. Un
changement de l'abonnement d'une organisation oublie les membres lus par elle.

## Places

```php
$offers->add(Offer::create('crew', 'studio', 3, 'Crew', Money::of(4_900, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['crew:board'], 5));

$seats->assign($subscription, $memberId, $clock->now());   // refused once the 5 seats are taken
$seats->release($subscription, $memberId);
$seats->holders($subscription);                            // earliest assigned first
```

- **Une offre sans places** ouvre ses fonctionnalités à chaque membre, et n'en prend aucune.
- **Une offre avec places** les ouvre aux membres qui détiennent l'une de ses premières places, les plus anciennes
  d'abord. L'organisation elle-même n'a besoin d'aucune place.
- **Après un changement vers un palier qui compte moins de places**, les places les plus anciennes gardent leurs
  fonctionnalités et les suivantes attendent, jusqu'à ce que des places soient libérées ou ajoutées ; aucune place
  n'est retirée à personne.
- Qui peut attribuer ou libérer une place relève de l'application. Un membre est désigné par un identifiant stable,
  jamais par une adresse courriel.

## Stockage

Avec DoctrineBundle, une place est une rangée de `phil_commerce_subscription_seat`, et les places d'une offre une
colonne de `phil_commerce_subscription_offer`.

| Colonne | Type | Contient |
|---|---|---|
| `subscription`, `member` | `CHAR(36)`, `VARCHAR(255)`, clé primaire ; `member` indexée | L'abonnement de l'organisation, et le membre qui détient la place |
| `assigned_at` | `DATETIME` | Le moment où la place a été attribuée |
| `seats`, des offres | `INTEGER`, nullable | Combien de places compte l'offre ; `null` pour chaque membre |
