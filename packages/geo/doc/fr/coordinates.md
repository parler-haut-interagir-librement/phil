<!-- phil-dev source-sha256=e391a9c257636905facd7f3d01b2d8644f91ee6276775c2f4aa24927bb9e1e98 -->
# Coordonnées

`Coordinates` est un point sur la Terre en degrés décimaux, WGS 84, tels que les donnent le GPS et les cartes.

```php
$point = Coordinates::of(45.5017, -73.5673);
$point = Coordinates::fromString(' 45.5017 , -73.5673 ');
$point->latitude;     // 45.5017
$point->toString();   // "45.5017,-73.5673"
```

| Règle | Détail |
|---|---|
| Latitude | De -90 à 90 ; toute autre valeur, ou NaN, lance `InvalidCoordinates` |
| Longitude | De -180 à 180 ; -180 est stocké comme 180, le même méridien, pour que des lieux égaux soient comparés comme égaux |
| Texte | `latitude,longitude`, en degrés décimaux, jusqu'à trois chiffres avant le point, espaces permises autour de la virgule |
| Écriture | Jusqu'à sept décimales (environ un centimètre), sans zéros à la fin |
| Égalité | `equals()` compare exactement les deux valeurs |

## Brouillage

`blurred($decimals)` arrondit les deux valeurs, de 0 à 7 décimales. Publier un lieu brouillé cache sa position exacte
tout en permettant de le trier par distance :

| Décimales | Environ |
|---|---|
| 1 | 11 km |
| 2 | 1.1 km |
| 3 | 110 m |
| 4 | 11 m |
