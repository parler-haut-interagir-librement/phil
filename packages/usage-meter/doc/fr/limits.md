<!-- phil-dev source-sha256=160cae77bca3d8721c7d1e2308fa602e9661e639f8148d1184c05bd84f3d32cf -->
# Limites

## `Limit`

Une limite est un nombre entier à partir de zéro, ou l'absence de limite :

```php
use Phil\UsageMeter\Limit;

Limit::of(3);
Limit::of(0);          // nothing allowed: a feature the tier lacks
Limit::unlimited();
Limit::parse('250');   // Limit::of(250)
Limit::parse('unlimited');
Limit::parse('many');  // null
```

`allows($used, $amount)`, `remaining($used)` (jamais sous zéro, `null` sans limite), `isUnlimited()`, `toString()`,
et `orHigher($other)`, la plus généreuse de deux limites, l'absence de limite battant n'importe quel nombre. `parse()`
lit `unlimited` ou jusqu'à 18 chiffres décimaux, sans signe ni zéro en tête.

## D'où vient la limite d'un sujet

`UsageMeter` demande à son `LimitProvider` la limite du sujet pour un compteur ; un fournisseur qui répond `null` n'a
rien à dire, et la limite propre au compteur s'applique :

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

`LimitChain` interroge plusieurs fournisseurs tour à tour, le premier qui nomme une limite l'emportant : une
dérogation fixée par un administrateur, puis l'abonnement, puis la limite propre au compteur :

```php
use Phil\UsageMeter\LimitChain;

new UsageMeter($meters, $store, $clock, new LimitChain($overrides, $subscriptionLimits));
```

La limite est lue à chaque appel : un abonné qui change de niveau reçoit la nouvelle limite aussitôt, sur la fenêtre
courante. Une mise à niveau libère ce que la nouvelle limite permet en plus de ce qui est utilisé ; un déclassement
sous l'utilisation refuse jusqu'à la fin de la fenêtre.

## Limites tirées des fonctionnalités d'abonnement

`Subscription\FeatureLimits` lit les limites écrites dans les fonctionnalités, `usage:<meter>:<limit>` :

| Fonctionnalité | Limite |
|---|---|
| `usage:exports:20` | 20 exportations par fenêtre du compteur `exports` |
| `usage:projects:unlimited` | aucune limite sur `projects` |
| `usage:ai.tokens:0` | aucun jeton du tout |

Avec `phil/commerce-subscription-bundle`, `fromSubscriptions()` lit les fonctionnalités de chaque offre pour laquelle
l'abonné détient un abonnement qui donne accès : en période d'essai, actif, ou en retard de paiement tant que durent
ses nouvelles tentatives :

```php
use Phil\UsageMeter\Subscription\FeatureLimits;

$limits = FeatureLimits::fromSubscriptions($featureAccess);
```

Un abonné qui détient plusieurs offres (un forfait et un module complémentaire) reçoit la limite la plus généreuse
qu'elles nomment. Celui dont les fonctionnalités n'en nomment aucune pour un compteur reçoit la limite propre au
compteur, celle du niveau gratuit : le niveau gratuit n'a donc besoin d'aucune offre. Une fonctionnalité dont la partie
après le préfixe n'est pas une limite (`usage:exports:many`) est sautée : l'erreur d'une offre ne bloque jamais
l'action d'un abonné. Le préfixe est le deuxième argument, `usage:` par défaut.

Sans ce bundle, donnez au constructeur n'importe quelle fermeture qui retourne les fonctionnalités d'un sujet :

```php
new FeatureLimits(fn (string $subject): array => $this->accounts->get($subject)->features);
```
