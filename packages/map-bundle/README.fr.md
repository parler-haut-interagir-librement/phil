<!-- phil-dev source-sha256=501bd01001e22b7b6914891a4da20ade6e8bb1d02b6c267fc0f9fbbf1d4cae78 -->
# phil/map-bundle

**Des cartes pour Symfony sur Symfony UX Map et Leaflet, avec des tuiles par lesquelles aucun serveur de tuiles ne peut suivre le visiteur.**

phil/map-bundle dessine trois sortes de cartes. Une carte de lieux montre des marqueurs là où `phil/geo` dit qu'ils sont
— salles, boutiques, points de rencontre —, ajustée à eux ou centrée autour d'un point. Une carte de quartier montre où
se trouvent des personnes, des lieux ou des groupes sans en montrer aucun : les positions sont comptées par cellule d'une
grille fixe, et seules les cellules qui en contiennent assez apparaissent, comme des zones avec leur compte. Un plan
d'étage montre une image — une salle, un lieu, un campus — sur le système de référence plat de Leaflet, avec ses kiosques
dessinés par-dessus, chacun dans l'état que sa ressource `phil/reservation` donne pour une période : libre, pris, fermé
ou inconnu.

Par défaut, le navigateur demande chaque tuile au site, et le site la prend à la source de tuiles sous son propre nom et
la conserve : la source voit le site, jamais le visiteur. Les tuiles peuvent aussi venir directement de la source, sans
référent ni témoins, ou d'une archive PMTiles que le site sert lui-même. Chaque carte vient avec une liste qui dit en
texte ce qu'elle montre, dessinée avec la recette Item du kit PHIL UX, et qui reste lorsque JavaScript ne s'exécute pas.

Il convient à tout site Symfony qui montre des lieux : un site d'événements avec ses salles, un club avec ses points de
rencontre, un annuaire de membres avec ses quartiers, un salon professionnel avec son plan d'étage. Le bundle s'appuie
sur Symfony UX Map et son pont Leaflet et les décore par leurs événements : il ajoute un contrôleur Stimulus à côté de
celui de Leaflet, jamais une carte qui lui soit propre.

## État

| Partie | État |
|---|---|
| `TileSource`, `SiteAddress`, `MapPlace` : le gabarit de tuile, les adresses, les lieux de `phil/geo` | Implémenté et testé |
| `ProxiedTiles` et le mandataire de tuiles : des tuiles par le site, conservées dans un pool de cache | Implémenté et testé |
| `DirectTiles`, `NoTiles` : des tuiles de la source sans référent ni témoins, ou aucune | Implémenté et testé |
| `PmtilesLayer` : une archive PMTiles auto-hébergée, raster ou vectorielle, son paquet trouvé dans l'import map | Implémenté et testé ; le repli piloté dans Chrome par la suite de navigateur |
| `PrivacyGrid`, `Neighbourhood` : des zones approximatives d'une grille fixe, les cellules sous le minimum écartées | Implémenté et testé |
| `FloorPlan`, `Booth`, `BoothAvailability` : un plan sur le système de référence plat de Leaflet et les états de ses kiosques | Implémenté et testé |
| `ReservationBoothAvailability` : les états tirés de `phil/reservation-bundle` | Implémenté et testé |
| `GdImageTiler` et `phil:map:tile-image` : de grandes images découpées en tuiles avec GD | Implémenté et testé |
| `Maps`, `MapView` et `phil_map()` : les cartes et leurs listes, avec la recette Item du kit | Implémenté et testé |
| Le contrôleur `map` : plans, archives et classes des kiosques et des zones | Implémenté ; module pur testé sous Node, contrôleur piloté dans Chrome par la suite de navigateur |
| La recette Flex : le bundle, les tuiles par le site et la route du mandataire | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, la route, le contrôleur, la recette du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services enregistrés. |
| [Cartes](doc/fr/maps.md) | Les lieux, le quartier et les plans d'étage, construits avec `Maps` et rendus avec `phil_map()`. |
| [Tuiles](doc/fr/tiles.md) | Le mandataire, les tuiles directes, les archives PMTiles, et ce que chacun dit à qui. |
| [Vie privée](doc/fr/privacy.md) | Pourquoi la couche de quartier emploie une grille fixe et un minimum, et ce qu'elle ne montre jamais. |
| [Plans d'étage](doc/fr/floor-plans.md) | Le système de référence plat, les kiosques et leurs états, les grandes images découpées en tuiles. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, les événements de Symfony UX Map qu'il écoute, et la feuille de style. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, et les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/map-bundle symfony/ux-leaflet-map
php bin/console ux:install item --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_map.yaml
phil_map:
    tiles:
        proxy:
            user_agent: 'example.org maps (maps@example.org)'
```

```php
$view = $maps->places('Our venues', [
    MapPlace::of('hall', 'Main hall', Coordinates::of(45.5017, -73.5673), 'Doors at 9', SiteAddress::of('/venues/hall')),
]);
```

```twig
{{ phil_map(view) }}
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2, Symfony UX Map 3.7 et son pont Leaflet, `phil/geo` et `phil/url`. Pour
le contrôleur, Stimulus 3 et AssetMapper ; pour la liste, Symfony UX Twig Component et la recette Item du kit PHIL UX.
Pour les plans d'étage découpés en tuiles, l'extension GD de PHP ; pour les états des kiosques,
`phil/reservation-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
