<!-- phil-dev source-sha256=21dc1a9d2290f1a510daa2e18a1c727aea973b032bec9225025cf634141a3918 -->
# Distances

## La distance orthodromique

`Coordinates::distanceTo()` est la distance haversine sur une sphère du rayon moyen de la Terre, 6 371 008.8 m. Elle se
situe à moins d'un demi pour cent de la distance sur l'ellipsoïde : assez pour trier des lieux et afficher « 2.7 km »,
pas pour de l'arpentage.

```php
$distance = $montreal->distanceTo($quebec);
$distance->meters;          // 233029.3
$distance->inKilometers();  // 233.03
$distance->inMiles();       // 144.8
```

`Distance` vaut zéro mètre ou plus, et est finie ; `kilometers()` et `miles()` en construisent une, `isShorterThan()` et
`compare()` les ordonnent.

## Trouver des lieux près d'un point

Une base de données trouve des lieux près d'un point en deux étapes : la zone englobante restreint la recherche sur un
index, puis la distance exacte garde ceux qui sont vraiment à portée.

```php
$box = BoundingBox::around($here, Distance::kilometers(10));
// WHERE latitude BETWEEN :south AND :north AND longitude BETWEEN :west AND :east
$near = array_filter($candidates, static fn (Store $store): bool => $store->position->distanceTo($here)->meters <= 10_000);
```

Près d'un pôle, ou quand la zone traverserait l'antiméridien, elle prend toutes les longitudes : la requête lit une
bande plus large, et la distance exacte décide quand même.
