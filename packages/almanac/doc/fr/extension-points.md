<!-- phil-dev source-sha256=9a821011f15c519c85237e93349a90520433ea93b71c41d5f7a962d0faaf80ef -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des règles; ce
qu'elle choisit, ce sont les définitions de son almanach et son fuseau horaire.

## `Rule`

`Phil\Almanac\Rule\Rule` est une façon de trouver des dates dans une année. Voir [Règles](rules.md).

| Méthode | Contrat |
|---|---|
| `in(int $year, DateTimeZone $zone): list<DateTimeImmutable>` | Ses dates dans cette année, dans l'ordre : des minuits dans le fuseau pour une règle de jours, des instants pour une règle du ciel |
| `isInstant(): bool` | Indique si elle trouve des instants (une phase de la Lune, un solstice) plutôt que des jours |
| `__toString(): string` | Son texte, pour la configuration ou une colonne |

`RuleParser` lit seulement les textes des règles fournies. Une règle propre à l'application est construite dans le
code, ou lue par l'analyseur de l'application avant de se replier sur `RuleParser::parse()`.

### `WithinTheYear`

`Phil\Almanac\Rule\WithinTheYear` est un marqueur pour une règle dont les dates tombent dans leur année, ou au plus à
une semaine de celle-ci. L'almanach lit alors seulement les années dont une période a besoin. Une règle qui ne
l'implémente pas est lue sur les deux années avant la période et aussi sur l'année d'après.

## Lieux

`SunTimes::on()` accepte n'importe quel `Place` : une application garde ses lieux (les coordonnées d'une salle, une ville
configurée) et les construit quand elle les demande. L'altitude du lieu n'est pas comptée; une application qui la veut
déplace elle-même l'horizon, à partir de `Sun::declination()` et de `Sun::equationOfTime()`.

## Almanachs

| Point | Comment |
|---|---|
| Les dates d'un almanach | Les `Definition` données à `Almanac` : clé, titre, règle, une règle de fin ou un nombre de jours, des jours entiers ou une heure, des étiquettes |
| Le fuseau horaire | Le `$zone` donné à `Almanac` : chaque date y est calculée |
| Les dates qu'une page montre | Les `$tags` de `Almanac::between()` |
