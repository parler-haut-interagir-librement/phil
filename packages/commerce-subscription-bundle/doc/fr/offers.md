<!-- phil-dev source-sha256=0fdcc73c9fcca659dca2dedcd83f6b1699050a636733a35a3fd59249f610ab76 -->
# Offres

Une offre est un palier d'une famille : un palier gratuit, des paliers payants et des paliers de soutien sont des
offres d'une même famille, classées, certaines à un prix nul. Un abonnement change de palier à l'intérieur de sa
famille.

```php
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\Money\Currency;
use Phil\Money\Money;

$offers->add(Offer::create('free', 'studio', 0, 'Free', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['projects:3']));
$offers->add(Offer::create('pro', 'studio', 1, 'Pro', Money::of(999, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 14, 'digital', ['projects:unlimited', 'export']));
```

| Argument de `Offer::create()` | Règle |
|---|---|
| `code`, `family` | Jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| `rank` | 0 ou plus : l'ordre des paliers dans la famille |
| `name` | Non vide : la description de la ligne de commande |
| `price` | Zéro ou plus, par période ; zéro rend le palier gratuit |
| `period` | `BillingPeriod::of(BillingUnit, int $count)` : de 1 à 36 jours, semaines, mois ou années |
| `trialDays` | De 0 à 365 |
| `taxCategory` | Non vide : la catégorie que lisent les taux de taxe du noyau |
| `features` | Des noms d'au plus 128 lettres minuscules, chiffres, `_`, `.`, `:` ou `-` ; les doublons sont écartés |
| `seats` | `null`, la valeur par défaut, pour chaque membre d'une organisation qui la détient ; ou de 1 à 100 000 places ([organizations.md](organizations.md)) |

- **`reprice(Money)`** fixe le prix des périodes commandées à partir de maintenant, dans la devise de l'offre ; les
  périodes déjà payées gardent le leur.
- **`withdraw()`** arrête les nouveaux abonnements et les changements vers ce palier, et le retire de la vente : aucun
  panier ne le vend plus. Les abonnements à ce palier continuent, avec ses fonctionnalités, et se renouvellent toujours
  à son prix.
- **Vendue par le noyau** : `OfferSellables` répond aux références de type `offer` — `offer:pro` est une période de
  `pro`, à son prix actuel, dans sa catégorie de taxe, tant que `pro` est en vente — et à celles de type
  `subscription` — `subscription:<id>` est la période suivante de cet abonnement, à son palier de renouvellement,
  retiré ou non, tant que l'abonnement n'attend aucune commande ; le nouveau palier pendant un changement sur-le-champ
  ([proration.md](proration.md)) —, et à celles de type `gift` — `gift:<id>` est une période de l'offre de ce cadeau,
  une unité par période offerte, tant que le cadeau attend sa commande ([gifts.md](gifts.md)).

## Périodes

**Les périodes sont comptées à partir d'un point d'ancrage, jamais enchaînées.** La fin de la n-ième période est le
point d'ancrage plus n périodes : un abonnement mensuel commencé le 31 janvier se termine donc le 28 février, puis le
31 mars, plutôt que de glisser au 28. Une année à partir du 29 février se termine le 28 février, et revient au 29
quatre ans plus tard. L'heure et le fuseau horaire du point d'ancrage sont gardés.

## Stockage

Avec DoctrineBundle, une offre est une rangée de `phil_commerce_subscription_offer`.

| Colonne | Type | Contient |
|---|---|---|
| `code` | `VARCHAR(64)`, clé primaire | Le code de l'offre |
| `family` | `VARCHAR(64)`, indexée | Sa famille |
| `tier_rank` | `SMALLINT` | Son rang dans la famille |
| `name` | `VARCHAR(255)` | Son nom |
| `price`, `currency` | `BIGINT`, `CHAR(3)` | Son prix par période, en unités mineures |
| `period_unit`, `period_count` | `VARCHAR(8)`, `SMALLINT` | Sa période |
| `trial_days` | `SMALLINT` | Son essai |
| `tax_category` | `VARCHAR(64)` | Sa catégorie de taxe |
| `features` | JSON | Les fonctionnalités qu'elle débloque |
| `active` | `BOOLEAN` | `false` une fois retirée |
| `seats` | `INTEGER`, nullable | Les places qu'elle compte ; `null` pour chaque membre |
| `dunning` | JSON | Ses étapes de relance ([dunning.md](dunning.md)) |
