<!-- phil-dev source-sha256=8c9de1cbbc2d35f8414bd8725605947d9702153a7b9fd84bcf03c8557cc00058 -->
# Points d'extension

Ce qu'une application configure, remplace, décore ou écoute. Les ports et les types valeur sont ceux de `phil/geo`.

## Géocodage

| Point | Comment |
|---|---|
| Les fournisseurs, dans l'ordre où ils sont interrogés | `geocoding.providers` et les clés de chaque fournisseur ; plusieurs forment un `ChainGeocoder` (voir [Géocodage](geocoding.md)) |
| Des réponses conservées un certain temps | `geocoding.cache.pool` et `lifetime` : un `CachedGeocoder` autour de la chaîne |
| Le localisateur IP | `ip_locator.maxmind` ou `ip_locator.ipstack` ; MaxMind l'emporte quand les deux sont configurés |

| Service | Comment une application l'utilise |
|---|---|
| `Phil\Geo\Geocoding\Geocoder` | Injecté partout où des adresses sont géocodées ; un alias du fournisseur, de la chaîne ou du cache. Une application qui a un fournisseur non fourni enregistre son propre `Geocoder` (par exemple un `ChainGeocoder` sur lui et sur les services `phil_geo.geocoder.<provider>` configurés) et fait pointer l'alias vers lui |
| `Phil\Geo\Geocoding\IpLocator` | Injecté pour localiser approximativement un visiteur ; remplacé de la même façon par celui de l'application |
| `phil_geo.geocoder.<provider>` | Un service par fournisseur configuré, pour une application qui les compose elle-même |

## Formulaires et stockage

| Point | Comment |
|---|---|
| L'endroit où une position est conservée | `Phil\Geo\Coordinates` comme objet intégrable de deux colonnes, mappé tant que `doctrine_mapping` est activé ; `false` pour un mappage propre à l'application (voir [Doctrine](doctrine.md)) |
| Plus que ce que fait le champ | Une extension de type de formulaire de l'application sur `CoordinatesType` (voir [Formulaires](forms.md)) |

## Twig et le navigateur

`geo_attributes()` écrit `data-latitude` et `data-longitude`, brouillés au nombre de décimales demandé. Le contrôleur
Stimulus `phil--geo-bundle--distance-sort` les lit sur ses cibles `item`, prend les valeurs `latitude`, `longitude`,
`locate`, `system` et `locale`, et émet `sorted`, avec l'origine, et `denied` quand le navigateur ne donne pas de
position (voir [Trier par distance](distance-sort.md)). Les fonctions de `distance.js` (`distance()`,
`sortByDistance()`, `formatDistance()`, `positionOf()`) sont exportées pour les propres contrôleurs d'une application.
