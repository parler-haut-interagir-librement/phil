<!-- phil-dev source-sha256=3b958fb64288ab1c176c7dcc6b11cfb1069117927848d0d7d730fef1aac9cdc7 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\MapBundle\PhilMapBundle` | Le bundle : sa configuration `phil_map`, ses services, les chemins d'AssetMapper et le moteur de rendu de Symfony UX Map. |
| `Domain\ValueObject\TileSource` | Un gabarit de tuile, son attribution et son zoom le plus profond ; l'adresse d'une tuile. |
| `Domain\ValueObject\SiteAddress` | Un chemin du site ou une adresse absolue lue par `phil/url`. |
| `Domain\ValueObject\MapPlace` | Un lieu : identifiant, nom, coordonnées, ligne, lien. |
| `Domain\ValueObject\PrivacyGrid` | La grille fixe de la couche de quartier : la taille d'une cellule et le minimum. |
| `Domain\ValueObject\GridCell` | Une cellule, par rangée et par colonne. |
| `Domain\ValueObject\ApproximateArea` | Une cellule telle qu'elle est montrée : coins, milieu, compte. |
| `Domain\ValueObject\FloorPlan` | Une image, sa taille, ses kiosques, ses tuiles ; son échelle sur le système de référence plat. |
| `Domain\ValueObject\Booth` | Un rectangle d'un plan et la ressource qui le représente dans les réservations. |
| `Domain\ValueObject\TilePyramid` | Ce que le découpeur a écrit : la taille, le zoom natif, le côté et le nombre de tuiles. |
| `Domain\Enum\BoothState` | `free`, `taken`, `closed`, `unknown`. |
| `Domain\Enum\TileMode` | `proxy`, `direct`, `pmtiles`, `none`. |
| `Domain\Exception\MapException` | Ce que toute exception du bundle implémente. |
| `Domain\Exception\InvalidMap` | Un lieu, une source, une grille, un kiosque ou un plan refusé. |
| `Domain\Exception\TileUnavailable` | Une tuile que la source n'a pas donnée comme le mandataire l'accepte. |
| `Domain\Exception\TilingFailed` | Une image qui n'a pu être découpée en tuiles. |
| `Application\Maps` | Construit les cartes de lieux, de quartier et de plans d'étage. |
| `Application\MapView` | Une carte prête à être rendue : la carte, son libellé, sa liste. |
| `Application\ListedPlace` | Une ligne de la liste : nom, ligne, lien. |
| `Application\Neighbourhood` | Compte les positions par cellule et garde les cellules qui atteignent le minimum. |
| `Application\MapMessages` | Les mots des cartes, tirés de `phil_map` ou en anglais. |
| `Application\UnknownBoothAvailability` | Chaque kiosque inconnu. |
| `Application\Contract\BaseLayer` | Les tuiles sous une carte géographique. |
| `Application\Contract\BoothAvailability` | Les états des kiosques sur une période. |
| `Application\Contract\ImageTiler` | Découpe une image en tuiles de plan. |
| `Application\Tiles\BaseLayerDefinition` | La couche de tuiles et la description du contrôleur que donne une couche de base. |
| `Application\Tiles\ProxiedTiles` | Les tuiles par le site. |
| `Application\Tiles\DirectTiles` | Les tuiles de la source, sans référent ni témoins. |
| `Application\Tiles\NoTiles` | Aucune tuile. |
| `Infrastructure\Http\TileProxyController` | Le mandataire de tuiles. |
| `Infrastructure\Pmtiles\PmtilesLayer` | L'adaptateur PMTiles. |
| `Infrastructure\Gd\GdImageTiler` | Le découpeur sur GD. |
| `Infrastructure\Reservation\ReservationBoothAvailability` | Les états des kiosques tirés de `phil/reservation-bundle`. |
| `Infrastructure\Console\TileImageCommand` | `phil:map:tile-image`. |
| `Infrastructure\Twig\MapExtension` | `phil_map()` et `phil_map_kit()`. |
| `Infrastructure\Twig\KitComponents` | Les noms sous lesquels les recettes du kit sont installées. |
| `Infrastructure\DependencyInjection\MapServices` | Enregistre les services. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les recettes d'après le sous-répertoire du Toolkit. |
| `Infrastructure\DependencyInjection\BoothAvailabilityPass` | Met en place la disponibilité de `phil/reservation-bundle` lorsqu'elle est un service. |

| Fichier | Rôle |
|---|---|
| `config/routes.php` | La route `phil_map_tile`. |
| `templates/map.html.twig` | Une carte, son libellé et sa liste. |
| `assets/src/map_controller.js` | Le contrôleur. |
| `assets/src/lib/plan.js` | Les décisions pures du contrôleur. |
| `assets/styles/map.css` | La feuille de style. |
| `translations/phil_map.{en,fr}.php` | Les mots. |
