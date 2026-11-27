<!-- phil-dev source-sha256=9007540344776208451287f94aea803759489b14e0d9ee7612bb7376ce80b1b4 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilGeoBundle` | `final class`, `AbstractBundle` | Alias `phil_geo` ; ajoute le mappage de `Coordinates` à l'ORM, enregistre le type de formulaire et l'extension Twig |
| `PhilGeoBundle::DOCTRINE_MAPPING` | `const string` | `phil_geo.doctrine_mapping`, le paramètre défini quand l'ORM est configuré et que `doctrine_mapping` est activé |
| `Infrastructure\Doctrine\CoordinatesMappingDriver` | `final readonly class`, `MappingDriver` de Doctrine | Associe `Coordinates` avec les attributs de `CoordinatesEmbeddable` |
| `Infrastructure\Doctrine\CoordinatesEmbeddable` | `final class`, `#[ORM\Embeddable]`, interne | Les attributs de `Coordinates` : deux colonnes `float`, `latitude` et `longitude` |
| `Infrastructure\Form\CoordinatesType` | type de formulaire | Une position en deux champs |
| `Infrastructure\Form\CoordinatesTransformer` | transformateur de données | Entre `Coordinates` et les deux champs |
| `Infrastructure\Twig\GeoExtension` | extension Twig | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` |
| `Infrastructure\DependencyInjection\GeocodingServices` | `final class` | Enregistre les géocodeurs, la chaîne, le cache et le localisateur IP de la configuration |
| `Infrastructure\MaxMind\DatabaseUpdater` | `final readonly class` | Télécharge la plus récente base MaxMind, la vérifie, la met en place ; une exécution à la fois, sous `<database>.lock` |
| `Infrastructure\MaxMind\UpdateFailed` | `final class`, `RuntimeException` | Une mise à jour qui a échoué ; la base en place est gardée |
| `Infrastructure\Console\UpdateMaxMindDatabaseCommand` | commande de console | `phil:geo:maxmind:update`, `--force` |
| `assets/src/distance.js` | module JavaScript | `distance()`, `sortByDistance()`, `formatDistance()`, `positionOf()` |
| `assets/src/distance_sort_controller.js` | contrôleur Stimulus | `distance-sort` |
