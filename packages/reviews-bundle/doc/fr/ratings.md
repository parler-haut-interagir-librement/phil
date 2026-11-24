<!-- phil-dev source-sha256=bcec57a16b37fe94203d0cffa2faf269e981b74ce551073f5e7d5698343fe72f -->
# Notes

Les notes de chaque sujet sont calculées à partir de ses avis publiés et conservées dans `phil_review_subject`, de sorte
qu'une page, une liste de produits ou un classement les lit sans compter les avis. `Phil\ReviewsBundle\Application\Ratings`
les lit et les calcule ; `RatingSummary` les contient.

| Partie de `RatingSummary` | Ce que c'est |
|---|---|
| `count`, `sum`, `mean()` | Le nombre d'avis publiés, la somme de leurs notes globales, et leur moyenne simple. |
| `weighted` | La moyenne pondérée sous l'a priori du type, ou `null` sans avis. |
| `criteria` | La moyenne de chaque critère sur les avis qui l'ont noté, avec le nombre d'avis qui l'ont fait (`CriterionRating`). |
| `superlatives` | La fréquence à laquelle chaque superlatif a été donné. |
| `foreign`, `allFromSite()` | Le nombre d'avis publiés qui n'ont pas été recueillis sur le site. |
| `suspended` | Si les nouveaux avis du sujet sont suspendus. |

## La moyenne pondérée

Un sujet avec deux avis parfaits ne devrait pas se classer au-dessus d'un sujet avec deux cents bons avis. La moyenne
pondérée ajoute `prior.count` avis fictifs de la valeur neutre `prior.mean` — le milieu de l'échelle par défaut — aux
avis réels :

```text
weighted = (mean × count + sum of the scores) / (count + number of reviews)
```

Avec les valeurs par défaut, cinq avis fictifs de 3 sur 5 : un sujet avec deux avis de 5 a une moyenne pondérée de
(15 + 10) / 7 ≈ 3,57 ; un sujet avec quarante avis dont la moyenne est 4,5 a (15 + 180) / 45 ≈ 4,33, et se classe
premier.

Ses paramètres sont publiés : le sommaire de chaque page dit combien d'avis fictifs de quelle valeur sont ajoutés, et à
partir de combien d'avis une moyenne est affichée (`prior.minimum`). En dessous de ce minimum, la page dit combien
d'avis le sujet compte, et n'affiche aucune moyenne.

## Les recalculer

Chaque changement d'un avis qui touche les notes — écrit, modifié, retiré, publié de nouveau, effacé — recalcule les
notes de son sujet, à partir des avis eux-mêmes plutôt qu'en ajoutant aux chiffres précédents, de sorte qu'un calcul
répété ou interrompu les laisse justes.

Un calcul prend d'abord la ligne de son sujet — une mise à jour que la base de données sérialise —, puis lit les avis et
écrit les notes dans la même transaction. Deux calculs d'un même sujet au même moment s'exécutent l'un après l'autre, et
le second lit ce que le premier ne pouvait pas voir : les dernières notes écrites ne manquent jamais un avis écrit avant
qu'elles soient calculées.

Après un changement de l'a priori ou du minimum d'un type, recalculez chaque sujet :

```sh
php bin/console phil:reviews:recompute          # every declared type
php bin/console phil:reviews:recompute game     # one type
```

## Les superlatifs

Le sommaire affiche les `superlatives_shown` libellés donnés le plus souvent, en ordre décroissant ; une égalité garde
l'ordre des libellés dans la configuration :

```php
$summary->topSuperlatives($type->superlatives, $type->superlativesShown); // ['quick-to-teach' => 12, 'gorgeous' => 7]
```

## Le classement

```php
$ratings->ranking('game', 10); // the ten subjects of the type with reviews, by decreasing weighted average
```

Une égalité est départagée par le nombre d'avis, puis par identifiant.

## Les données structurées

Avec `phil/structured-data` installé, `AggregateRatings` donne le `AggregateRating` de schema.org d'un sujet, que
l'application pose comme `aggregateRating` du nœud de son produit, de son jeu ou de son lieu :

```php
$node = $aggregateRatings->of($subject); // ?Phil\StructuredData\Node

if (null !== $node) {
    $collector->add(Product::of('Catan', id: $url.'#product', properties: ['aggregateRating' => $node]));
}
```

```twig
{% set rating = phil_reviews_aggregate_rating('game', game.id) %}
```

Il donne `ratingValue` — la moyenne simple, arrondie à deux décimales —, `bestRating`, `worstRating` et `ratingCount`.
Il n'y en a aucun :

- sans avis publié, ou en dessous du minimum du type ;
- quand un avis publié du sujet n'a pas été recueilli sur le site : un avis que l'application a apporté d'un autre
  système à elle porte `Origin::Transferred`, et le sujet n'a alors aucune donnée structurée. Le bundle ne va jamais
  chercher d'avis sur une autre plateforme.
