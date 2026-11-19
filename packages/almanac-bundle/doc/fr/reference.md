<!-- phil-dev source-sha256=414267ff6bb70bf228c624f9f00ab32d1c25b5c28a48146009472c35fbc3f836 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilAlmanacBundle` | `final class`, `AbstractBundle` | Alias `phil_almanac` ; la configuration, le mappage et les services |
| `Domain\Model\StoredDate` | entité | Une date dans `phil_almanac_date` : `definition()`, `change()` |
| `Domain\Repository\StoredDateRepository` | port | `all()`, `ofKey()`, `save()`, `remove()` |
| `Infrastructure\Doctrine\DoctrineStoredDateRepository` | `StoredDateRepository` | Sur Doctrine |
| `Infrastructure\Doctrine\BinaryKeysListener` | écouteur | `date_key` en `utf8mb4_bin` sur MySQL et MariaDB, au `loadClassMetadata` de l'ORM, avec `database: true` ([stored-dates.md](stored-dates.md#collation-de-la-clé)) |
| `Application\Definitions` | `final readonly class` | Les dates configurées et stockées : `all()`, `isConfigured()`, `stored()` |
| `Application\ConfiguredDates` | `final class` | Les dates configurées, lues : `check()` à la construction, `definitions()` pour le service, `eclipses()` pour les éclipses déclarées |
| `Application\AlmanacFactory` | `final readonly class` | `almanac()`, construit à la demande |
| `Application\DateDraft` | `final class` | Une date telle que la remplit un formulaire : `of()`, `definition()` |
| `Infrastructure\Form\StoredDateType`, `TagsTransformer` | type de formulaire, transformateur de données | Le formulaire d'une date stockée |
| `Application\Places` | `final readonly class` | Les lieux configurés : `get()`, `names()` |
| `Infrastructure\Twig\AlmanacExtension` | extension Twig | `almanac_dates` |
| `Infrastructure\Twig\SkyExtension` | extension Twig | `sun_times`, `almanac_eclipses` |

| Service | |
|---|---|
| `Phil\Almanac\Almanac` | Non partagé : construit à partir des définitions à chaque injection ; injectez `AlmanacFactory`, ou une fermeture de service, dans un service de longue durée |
| `Phil\AlmanacBundle\Application\Definitions`, `AlmanacFactory`, `Places` | Publics |
| `Phil\AlmanacBundle\Domain\Repository\StoredDateRepository` | Alias de `DoctrineStoredDateRepository`, avec `database: true` |
