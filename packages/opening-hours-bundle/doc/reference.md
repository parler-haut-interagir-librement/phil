# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilOpeningHoursBundle` | `final class`, `AbstractBundle` | Alias `phil_opening_hours`, key `default_timezone`; the services and the column type |
| `Infrastructure\Form\OpeningHoursType` | form type | The hours form |
| `Infrastructure\Form\OpeningHoursTransformer`, `DayHoursTransformer`, `SpecialHoursTransformer` | data transformers | Between the hours and the fields |
| `Infrastructure\Doctrine\OpeningHoursColumnType` | DBAL type | `phil_opening_hours` |
| `Infrastructure\Twig\OpeningHoursExtension` | Twig extension | The `opening_hours_*` functions |
| `Infrastructure\Intl\Intl` | `final class` | Days, dates and times written by ICU |
| `templates/table.html.twig` | template | The table of hours |
