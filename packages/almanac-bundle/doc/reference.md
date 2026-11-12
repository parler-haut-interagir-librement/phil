# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilAlmanacBundle` | `final class`, `AbstractBundle` | Alias `phil_almanac`; the configuration, the mapping and the services |
| `Domain\Model\StoredDate` | entity | A date in `phil_almanac_date`: `definition()`, `change()` |
| `Domain\Repository\StoredDateRepository` | port | `all()`, `ofKey()`, `save()`, `remove()` |
| `Infrastructure\Doctrine\DoctrineStoredDateRepository` | `StoredDateRepository` | On Doctrine |
| `Application\Definitions` | `final readonly class` | The configured and stored dates: `all()`, `isConfigured()`, `stored()` |
| `Application\ConfiguredDates` | `final class` | The configured dates read: `check()` at build, `definitions()` for the service, `eclipses()` for the declared eclipses |
| `Application\AlmanacFactory` | `final readonly class` | `almanac()`, built when asked for |
| `Application\DateDraft` | `final class` | A date as a form fills it: `of()`, `definition()` |
| `Infrastructure\Form\StoredDateType`, `TagsTransformer` | form type, data transformer | The form of a stored date |
| `Application\Places` | `final readonly class` | The configured places: `get()`, `names()` |
| `Infrastructure\Twig\AlmanacExtension` | Twig extension | `almanac_dates` |
| `Infrastructure\Twig\SkyExtension` | Twig extension | `sun_times`, `almanac_eclipses` |

| Service | |
|---|---|
| `Phil\Almanac\Almanac` | Not shared: built from the definitions at each injection; inject `AlmanacFactory`, or a service closure, into a long-lived service |
| `Phil\AlmanacBundle\Application\Definitions`, `AlmanacFactory`, `Places` | Public |
| `Phil\AlmanacBundle\Domain\Repository\StoredDateRepository` | Alias of `DoctrineStoredDateRepository`, with `database: true` |
