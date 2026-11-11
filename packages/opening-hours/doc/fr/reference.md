<!-- phil-dev source-sha256=1919a6bc160a186aeb929f62cccc62664a6aff050e3fa441b1cdb031d63dbc93 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `OpeningHours` | `final readonly class` | La semaine, les heures spéciales et le fuseau horaire ; ouvert maintenant, prochaine ouverture, suites |
| `DayHours` | `final readonly class` | Les plages d'un jour |
| `TimeRange` | `final readonly class` | Quand un lieu ouvre et ferme |
| `TimeOfDay` | `final readonly class` | Une heure à la minute |
| `SpecialHours` | `final readonly class` | Des heures qui remplacent celles de la semaine pour une période |
| `DayOfWeek` | enum adossée à un entier | Les jours ISO et leurs noms schema.org |
| `HoursText` | `final class` | La forme texte |
| `OpeningHoursSchema` | `final class` | L'`openingHoursSpecification` de schema.org |
| `OpeningHoursArray` | `final class` | Les heures vers un tableau et à partir de celui-ci, pour le stockage |
| `Exception\OpeningHoursException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidOpeningHours` | `final class`, `InvalidArgumentException` | Des heures impossibles |
| `Exception\UnreadableOpeningHours` | `final class`, `UnexpectedValueException` | Des heures stockées qui ne peuvent pas être relues |
