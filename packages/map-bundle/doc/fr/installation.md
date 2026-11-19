<!-- phil-dev source-sha256=319590e1b88bf5990af4c076bb300766587de0fbe5238d89d8ba9d996f79c309 -->
# Installation

```sh
composer require phil/map-bundle symfony/ux-leaflet-map
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_map.yaml` et importe la route du
mandataire de tuiles sous `/_map`. Sans elle, enregistrez le bundle dans `config/bundles.php` :

```php
Phil\MapBundle\PhilMapBundle::class => ['all' => true],
```

et importez la route :

```yaml
# config/routes/phil_map.yaml
phil_map:
    resource: '@PhilMapBundle/config/routes.php'
    prefix: /_map
```

## Symfony UX Map

Le bundle dessine avec Symfony UX Map et son pont Leaflet : leurs recettes enregistrent `UXMapBundle` et ajoutent
Leaflet et le contrôleur du pont à l'import map. Le bundle fixe `ux_map.renderer` à `leaflet://default`, sauf si
l'application nomme un autre moteur de rendu.

## Le contrôleur et la feuille de style

Avec AssetMapper, le contrôleur du bundle est servi par le bundle sous le nom `@phil/map-bundle/map_controller.js` et sa
feuille de style sous le nom `phil-map/map.css`, sans étape de construction. Symfony Flex ajoute le contrôleur à
`assets/controllers.json` à partir du `assets/package.json` du bundle ; ajoutez la feuille de style à la page :

```twig
<link rel="stylesheet" href="{{ asset('phil-map/map.css') }}">
```

## La liste sous chaque carte

La liste est dessinée avec la recette Item du kit PHIL UX, installée dans l'application par le Symfony UX Toolkit ;
rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install item --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le gabarit l'appelle par `phil_map_kit('Item')`, sous le sous-répertoire des composants de la configuration
`ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans
`kit_subdirectory`.

## Paquets facultatifs

| Paquet | Pour |
|---|---|
| `ext-gd` | `phil:map:tile-image`, qui découpe une grande image en tuiles de plan d'étage |
| `phil/reservation-bundle` | Les états des kiosques, tirés des ressources qui les représentent dans les réservations |
| `pmtiles`, `protomaps-leaflet` dans l'import map | Une archive PMTiles, raster ou vectorielle : `php bin/console importmap:require pmtiles` |

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Symfony UX Map 3.7 ou une version plus
récente.
