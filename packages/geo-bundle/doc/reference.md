# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilGeoBundle` | `final class`, `AbstractBundle` | Alias `phil_geo`; prepends the embeddable, registers the form type and the Twig extension |
| `config/doctrine/Coordinates.orm.xml` | Doctrine mapping | `Coordinates` as an embeddable on two columns |
| `Infrastructure\Form\CoordinatesType` | form type | A position as two fields |
| `Infrastructure\Form\CoordinatesTransformer` | data transformer | Between `Coordinates` and the two fields |
| `Infrastructure\Twig\GeoExtension` | Twig extension | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` |
| `Infrastructure\DependencyInjection\GeocodingServices` | `final class` | Registers the geocoders, the chain, the cache and the IP locator of the configuration |
| `Infrastructure\MaxMind\DatabaseUpdater` | `final readonly class` | Downloads the latest MaxMind database, checks it, puts it in place; one run at a time, under `<database>.lock` |
| `Infrastructure\MaxMind\UpdateFailed` | `final class`, `RuntimeException` | An update that failed; the database in place is kept |
| `Infrastructure\Console\UpdateMaxMindDatabaseCommand` | console command | `phil:geo:maxmind:update`, `--force` |
| `assets/src/distance.js` | JavaScript module | `distance()`, `sortByDistance()`, `formatDistance()`, `positionOf()` |
| `assets/src/distance_sort_controller.js` | Stimulus controller | `distance-sort` |
