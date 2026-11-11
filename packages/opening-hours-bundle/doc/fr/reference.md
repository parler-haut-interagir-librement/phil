<!-- phil-dev source-sha256=2c210aa1e73feb5af75c535ce4e28dc22752ecada82c6e710a4897f3d2f4a068 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilOpeningHoursBundle` | `final class`, `AbstractBundle` | Alias `phil_opening_hours`, clé `default_timezone` ; les services et le type de colonne |
| `Infrastructure\Form\OpeningHoursType` | type de formulaire | Le formulaire des heures |
| `Infrastructure\Form\OpeningHoursTransformer`, `DayHoursTransformer`, `SpecialHoursTransformer` | transformateurs de données | Entre les heures et les champs |
| `Infrastructure\Doctrine\OpeningHoursColumnType` | type DBAL | `phil_opening_hours` |
| `Infrastructure\Twig\OpeningHoursExtension` | extension Twig | Les fonctions `opening_hours_*` |
| `Infrastructure\Intl\Intl` | `final class` | Les jours, les dates et les heures écrits par ICU |
| `templates/table.html.twig` | gabarit | Le tableau des heures |
