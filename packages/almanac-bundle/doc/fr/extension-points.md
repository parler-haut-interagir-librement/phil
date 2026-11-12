<!-- phil-dev source-sha256=a3617ee90eb807e97416fc306f5523113e984914e9ab177337a1c19ea741e11a -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise. Les règles et l'almanach sont ceux de `phil/almanac`.

## `StoredDateRepository`

`Phil\AlmanacBundle\Domain\Repository\StoredDateRepository` — les dates que conserve un administrateur, qui remplacent
les dates configurées de leur clé. Avec `database: true`, c'est `DoctrineStoredDateRepository`, sur `phil_almanac_date`.
Voir [Dates stockées et formulaire](stored-dates.md).

| Méthode | Contrat |
|---|---|
| `all(): array<string, StoredDate>` | Chaque date stockée, par clé |
| `ofKey(string $key): ?StoredDate` | Une date |
| `save(StoredDate $date): void` | La conserve ; l'almanach suivant l'inclut |
| `remove(StoredDate $date): void` | L'oublie ; la date configurée de sa clé revient |

Un dépôt propre à l'application qui garde ce qu'il a lu se réinitialise entre les requêtes (`kernel.reset`), comme celui
qui est fourni : un *worker* voit ainsi les dates enregistrées par un autre processus.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `dates` | Les dates de l'almanach, chacune avec une clé, un titre, toute règle de `phil/almanac`, une fin ou un nombre de jours, et des étiquettes |
| `timezone` | Le fuseau dans lequel chaque date se calcule |
| `database` | Si les dates stockées s'ajoutent aux dates configurées et les remplacent |
| `eclipses` | Si les éclipses solaires et lunaires sont des dates de l'almanach, sous quels titres et quelles étiquettes |
| `places` | Les lieux que `sun_times()` connaît par leur nom |

Voir [Configuration](configuration.md).

## Services, formulaires et Twig

| Point | Comment |
|---|---|
| L'almanach dans le code | `AlmanacFactory::almanac()`, demandé à chaque utilisation par un service de longue durée plutôt que conservé |
| Les dates qui viennent de la configuration | `Definitions::isConfigured()` et `stored()`, pour une page d'administration |
| Le formulaire d'une date stockée | `StoredDateType` et son option `key_locked` ; libellés dans le domaine `phil_almanac`, que l'application remplace |
| Les dates dans une page | `almanac_dates(from, until, tags)` — voir [Twig](twig.md) |
| Le Soleil d'un lieu dans le code | `Places::get($name)`, puis `SunTimes::on()` de `phil/almanac` ; un lieu que l'application garde ailleurs, comme les coordonnées d'une salle, est un `Place` donné à `sun_times()` |
| Les éclipses dans une page | `almanac_eclipses(from, until, body)` |
