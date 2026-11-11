<!-- phil-dev source-sha256=0f9ad4931252ef225a077f7b69148ffe4b1814480d994302c7d6011febcb29dd -->
# phil/geo

**Des coordonnées, des distances et des zones englobantes sous forme de valeurs PHP, et le géocodage par le fournisseur
de votre choix.**

`phil/geo` représente un point sur la Terre par `Coordinates`, en degrés décimaux du WGS 84. Il mesure la distance
orthodromique entre deux points sous forme de `Distance` en mètres, en kilomètres ou en milles, et calcule la
`BoundingBox` à laquelle une base de données restreint une recherche, pôles et antiméridien compris. Il brouille un
point en réduisant ses décimales, pour publier un lieu sans sa position exacte, et écrit des coordonnées sous forme de
`GeoCoordinates` de schema.org. **Il ne fait pas** de cartes, ne projette pas de coordonnées et ne connaît aucun lieu :
tout cela relève de l'application.

Il s'adresse aux applications PHP qui stockent des lieux et les affichent par distance : un répertoire de magasins, un
calendrier d'événements, une liste de salles. Le géocodage passe par deux ports. `Geocoder` va d'une adresse à des
lieux, et inversement ; `IpLocator` va d'une adresse IP à un lieu approximatif. Des adaptateurs sur le client HTTP de
Symfony couvrent Nominatim, Photon, Google Maps, Ressources naturelles Canada, OpenCage, HERE, Mapbox, Azure Maps,
Adresses Québec et ipstack, avec une chaîne qui interroge chacun à tour de rôle et un cache. `MaxMindLocator` lit les
fichiers MaxMind DB, dont GeoLite2 City, sur le serveur même : une adresse IP consultée ne le quitte donc jamais.

`phil/geo` est une simple bibliothèque PHP qui n'exige que `ext-filter` ; `symfony/http-client` et `symfony/cache` ne
sont nécessaires qu'aux adaptateurs qui les utilisent. Dans une application Symfony, utilisez `phil/geo-bundle` : il
mappe les coordonnées dans Doctrine, ajoute un type de formulaire et des fonctions Twig, câble les géocodeurs à partir
de la configuration et trie les lieux par distance dans le navigateur.

La conception repose sur des types valeur immuables et sur des ports et des adaptateurs. Les ports et `Place` ne nomment
rien d'autre que cette bibliothèque : un modèle de domaine peut donc les contenir. Le lecteur MaxMind suit la
spécification du format et lit le fichier là où mène chaque recherche, sans jamais le charger en entier.

## État

| Partie | État |
|---|---|
| `Coordinates` : validation, lecture et écriture de `lat,lng`, égalité, brouillage | Implémenté et testé |
| Distance orthodromique (haversine), `Distance` en mètres, kilomètres et milles | Implémenté et testé |
| `BoundingBox` autour d'un point, pôles et antiméridien compris | Implémenté et testé |
| Exceptions sous une seule interface `GeoException` | Implémenté et testé |
| Géocodage : `Geocoder`, `IpLocator`, `Place` ; Nominatim, Photon, Google Maps, Ressources naturelles Canada, OpenCage, HERE, Mapbox, Azure Maps, Adresses Québec, ipstack, chaîne et cache | Implémenté et testé |
| Fichiers MaxMind DB lus sur le serveur ; `MaxMindLocator` pour GeoLite2 et GeoIP2 City | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Coordonnées](doc/fr/coordinates.md) | `Coordinates` : bornes, texte, égalité, brouillage. |
| [Distances](doc/fr/distances.md) | Les distances orthodromiques, `Distance`, les zones englobantes et la recherche de lieux près d'un point. |
| [Géocodage](doc/fr/geocoding.md) | Les ports, les lieux, les adaptateurs, la chaîne et le cache. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/geo
```

```php
use Phil\Geo\Coordinates;

$montreal = Coordinates::of(45.5017, -73.5673);
$quebec = Coordinates::fromString('46.8139,-71.208');

$montreal->distanceTo($quebec)->inKilometers(); // 233.03
$montreal->blurred(2)->toString();              // "45.5,-73.57"
```

## Prérequis

PHP 8.5 ou plus récent avec l'extension filter ; `symfony/http-client` pour les adaptateurs de géocodage,
`symfony/cache` pour `CachedGeocoder`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
