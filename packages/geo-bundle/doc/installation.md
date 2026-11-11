# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `ext-intl` and `ext-zlib` | The floor the bundle declares; `format_distance` writes numbers in the locale; `phil:geo:maxmind:update` decompresses the archive |
| `phil/geo` | ^0.1@dev | The value types |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `CoordinatesType` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration tree |
| `doctrine/orm` | optional | `Coordinates` as an embeddable |
| `twig/twig` | optional | The Twig functions and filter |
| `@hotwired/stimulus` | ^3.0, the application's | The `distance-sort` controller |

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

## Configuring

```yaml
# config/packages/phil_geo.yaml
phil_geo:
    doctrine_mapping: true   # maps Phil\Geo\Coordinates as an embeddable when the ORM is configured
```

## The Stimulus controller

The controller ships in `assets/`, declared in `assets/package.json` as `distance-sort`. With Symfony UX and the
AssetMapper or Webpack Encore, add the package to `assets/controllers.json`; it is then named
`phil--geo-bundle--distance-sort`. See [distance-sort.md](distance-sort.md).
