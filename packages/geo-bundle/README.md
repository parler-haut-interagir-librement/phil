# phil/geo-bundle

**Stored coordinates, distances in Twig, configured geocoders and nearest-first lists for Symfony applications.**

`phil/geo-bundle` brings `phil/geo` into a Symfony application. It maps `Coordinates` as a Doctrine embeddable of two
`float` columns, adds `CoordinatesType` to enter a position as two fields, and gives Twig `geo_distance()`,
`format_distance`, `geo_attributes()` and `geo_schema()`. It registers the geocoders the configuration lists, asked in
that order and optionally cached, and an IP locator on MaxMind or ipstack. The command `phil:geo:maxmind:update`
downloads the latest MaxMind database, checks it and puts it in place.

It is for Symfony applications where editors enter positions and visitors look for the nearest place. The Stimulus
controller `distance-sort` sorts places by their distance to the visitor **in the browser**. The browser asks the
visitor for the position, and that position never reaches the server. The server publishes the places, blurred when
their exact position is private, and knows nothing of who is near which.

Use the bundle when the application runs on Symfony; in other PHP code, or inside a domain model, use `phil/geo`
directly. The Doctrine mapping needs the ORM and can be turned off with `doctrine_mapping`. The bundle adds no
geography of its own: each piece wraps a library type, and the browser computes the same great-circle distance as
`Coordinates::distanceTo()`.

## Status

| Part | State |
|---|---|
| `Coordinates` as a Doctrine embeddable, optional | Implemented and tested |
| `CoordinatesType`: a position as two fields | Implemented and tested |
| Twig: `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` | Implemented and tested |
| The Stimulus controller `distance-sort` | Implemented and tested |
| Geocoders from the configuration — chained, cached — and the IP locator, MaxMind or ipstack | Implemented and tested |
| `phil:geo:maxmind:update`: the latest MaxMind database, checked and put in place | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, configuring, the Stimulus controller. |
| [Doctrine](doc/doctrine.md) | Embedding a position, searching near a point. |
| [Forms](doc/forms.md) | `CoordinatesType`. |
| [Twig](doc/twig.md) | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()`. |
| [Sorting by distance](doc/distance-sort.md) | The Stimulus controller, and why the position stays in the browser. |
| [Geocoding](doc/geocoding.md) | The providers of the configuration, the chain, the cache, the IP locator. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

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

```twig
<div data-controller="phil--geo-bundle--distance-sort" data-phil--geo-bundle--distance-sort-locate-value="true">
    {% for store in stores %}
        <article {{ geo_attributes(store.position, 3) }}>{{ store.name }} <span data-distance></span></article>
    {% endfor %}
</div>
```

## Requirements

PHP 8.5 or later with `ext-intl`, and `ext-zlib` for `phil:geo:maxmind:update`; `phil/geo` 0.1; `symfony/config`, `symfony/dependency-injection`, `symfony/form`
and `symfony/options-resolver` 8.2 or later. Doctrine ORM and Twig are optional; the controller needs
`@hotwired/stimulus` 3, which the application provides.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
