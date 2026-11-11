<!-- phil-dev source-sha256=3b7c9cabe8200080a0a25cc502269c0b657e05c12861bf3c1c0d1b6ae4b3a113 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilGeoBundle` | `final class`, `AbstractBundle` | Alias `phil_geo` ; ajoute l'objet intégrable en préfixe, enregistre le type de formulaire et l'extension Twig |
| `config/doctrine/Coordinates.orm.xml` | mappage Doctrine | `Coordinates` comme objet intégrable sur deux colonnes |
| `Infrastructure\Form\CoordinatesType` | type de formulaire | Une position en deux champs |
| `Infrastructure\Form\CoordinatesTransformer` | transformateur de données | Entre `Coordinates` et les deux champs |
| `Infrastructure\Twig\GeoExtension` | extension Twig | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` |
| `Infrastructure\DependencyInjection\GeocodingServices` | `final class` | Enregistre les géocodeurs, la chaîne, le cache et le localisateur IP de la configuration |
| `Infrastructure\MaxMind\DatabaseUpdater` | `final readonly class` | Télécharge la plus récente base MaxMind, la vérifie, la met en place ; une exécution à la fois, sous `<database>.lock` |
| `Infrastructure\MaxMind\UpdateFailed` | `final class`, `RuntimeException` | Une mise à jour qui a échoué ; la base en place est gardée |
| `Infrastructure\Console\UpdateMaxMindDatabaseCommand` | commande de console | `phil:geo:maxmind:update`, `--force` |
| `assets/src/distance.js` | module JavaScript | `distance()`, `sortByDistance()`, `formatDistance()`, `positionOf()` |
| `assets/src/distance_sort_controller.js` | contrôleur Stimulus | `distance-sort` |
