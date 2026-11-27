# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilGeoBundle` | `final class`, `AbstractBundle` | Alias `phil_geo`; adds the mapping of `Coordinates` to the ORM, registers the form type and the Twig extension |
| `PhilGeoBundle::DOCTRINE_MAPPING` | `const string` | `phil_geo.doctrine_mapping`, the parameter set when the ORM is configured and `doctrine_mapping` is on |
| `Infrastructure\Doctrine\CoordinatesMappingDriver` | `final readonly class`, Doctrine `MappingDriver` | Maps `Coordinates` with the attributes of `CoordinatesEmbeddable` |
| `Infrastructure\Doctrine\CoordinatesEmbeddable` | `final class`, `#[ORM\Embeddable]`, internal | The attributes of `Coordinates`: two `float` columns, `latitude` and `longitude` |
| `Infrastructure\Form\CoordinatesType` | form type | A position as two fields |
| `Infrastructure\Form\CoordinatesTransformer` | data transformer | Between `Coordinates` and the two fields |
| `Infrastructure\Twig\GeoExtension` | Twig extension | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` |
| `Infrastructure\DependencyInjection\GeocodingServices` | `final class` | Registers the geocoders, the chain, the cache and the IP locator of the configuration |
| `Infrastructure\MaxMind\DatabaseUpdater` | `final readonly class` | Downloads the latest MaxMind database, checks it, puts it in place; one run at a time, under `<database>.lock` |
| `Infrastructure\MaxMind\UpdateFailed` | `final class`, `RuntimeException` | An update that failed; the database in place is kept |
| `Infrastructure\Console\UpdateMaxMindDatabaseCommand` | console command | `phil:geo:maxmind:update`, `--force` |
| `assets/src/distance.js` | JavaScript module | `distance()`, `sortByDistance()`, `formatDistance()`, `positionOf()` |
| `assets/src/distance_sort_controller.js` | Stimulus controller | `distance-sort` |
