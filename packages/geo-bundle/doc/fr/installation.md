<!-- phil-dev source-sha256=8383083df93ae81549cf9a9549255794b5ed3f02671d8a5ff2142b81bb941330 -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `ext-intl` et `ext-zlib` | Le plancher que déclare le bundle ; `format_distance` écrit les nombres dans la locale ; `phil:geo:maxmind:update` décompresse l'archive |
| `phil/geo` | ^0.1@dev | Les types valeur |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `CoordinatesType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |
| `doctrine/orm` | facultatif | `Coordinates` comme objet intégrable |
| `twig/twig` | facultatif | Les fonctions et le filtre Twig |
| `@hotwired/stimulus` | ^3.0, celui de l'application | Le contrôleur `distance-sort` |

```sh
composer require phil/geo-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\GeoBundle\PhilGeoBundle::class => ['all' => true],
];
```

## Configurer

```yaml
# config/packages/phil_geo.yaml
phil_geo:
    doctrine_mapping: true   # maps Phil\Geo\Coordinates as an embeddable when the ORM is configured
```

## Le contrôleur Stimulus

Le contrôleur est fourni dans `assets/`, déclaré dans `assets/package.json` sous le nom `distance-sort`. Avec Symfony UX
et AssetMapper ou Webpack Encore, ajoutez le paquet à `assets/controllers.json` ; il s'appelle alors
`phil--geo-bundle--distance-sort`. Voir [distance-sort.md](distance-sort.md).
