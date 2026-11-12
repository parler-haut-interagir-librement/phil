<!-- phil-dev source-sha256=b8d2af2331c25530ff15db5579e76a96075ac5763ba4d3631685d834399f7c5f -->
# Référence

Chaque type public de la librairie, sous `Phil\LocaleDimensions`. Chacun est un objet valeur final et immuable,
construit par un constructeur nommé qui refuse ce qui ne peut pas exister par `Exception\InvalidLocaleDimension`.

| Type | Rôle |
|---|---|
| `Locale` | Une langue, restreinte au besoin à une région |
| `Version` | Une version comptée à partir de un |
| `FallbackChain` | Les locales essayées, dans l'ordre, pour une locale demandée |
| `DeclaredLocales` | Les locales que sert une application, chacune avec sa chaîne |
| `Exception\InvalidLocaleDimension` | Une valeur refusée à sa construction |

## `Locale`

`Locale::fromString(string)` accepte **une seule graphie** : une langue de deux ou trois lettres minuscules, suivie au
besoin d'un trait de soulignement et d'une région de deux lettres majuscules (`fr`, `fil`, `fr_CA`), comme l'écrivent
Symfony et `intl`. Les lignes sont trouvées par égalité sur cette chaîne : admettre `fr-CA` ou `fr_ca` à côté de
`fr_CA` créerait des lignes qu'une recherche manquerait. Toute autre forme est refusée, jamais normalisée.

| Membre | Renvoie |
|---|---|
| `$value`, `$language`, `$region` | `fr_CA`, `fr`, `CA` (`null` sans région) |
| `sharesLanguageWith(Locale)` | si les deux ont la même langue : `fr` et `fr_CA` oui, `fr_CA` et `en_CA` non |
| `toLanguageTag()` | l'étiquette BCP 47, `fr-CA`, que lisent `hreflang`, `lang` et `Content-Language` |
| `equals(Locale)`, `toString()`, `__toString()` | comparaison et forme en chaîne |

Les locales qui existent relèvent de la décision de l'application ; `Locale` refuse seulement ce qui ne peut pas en
être une. Les écritures (`zh_Hant`) et les régions numériques (`es_419`) ne sont pas acceptées.

## `Version`

`Version` compte à partir de un : `Version::first()`, `Version::of(int)`, `next()`, `isAfter(Version)`,
`equals(Version)`, et la propriété publique `$number`. `Version::of()` refuse zéro et les nombres négatifs, quelle que
soit leur source : une version zéro se lirait comme « pas encore de version » à un endroit et comme une vraie ligne à
un autre.

**Une version publiée n'est jamais modifiée.** La modifier crée la version suivante, et la version publiée reste telle
qu'elle a été approuvée ou envoyée. L'événement qui fige une version relève de la décision de l'application ; la
numérotation, non.

## `FallbackChain`

La liste ordonnée des locales essayées pour une locale demandée, la locale demandée en premier.

| Membre | Renvoie |
|---|---|
| `FallbackChain::of(array)` | la chaîne ; refuse une liste vide et une locale qui apparaît deux fois |
| `$locales` | les locales, la locale demandée en premier |
| `requested()` | la locale demandée |
| `resolve(iterable)` | la première locale de la chaîne parmi celles données, ou `null` |
| `leavesRequestedLanguage(Locale)` | si servir cette locale quitte la langue demandée |
| `toString()` | `fr_CA → fr → en` |

- **La chaîne est déclarée, jamais déduite.** Retirer la région (`fr_CA → fr`) puis se replier sur une langue par
  défaut a l'air général et se trompe dès que deux variantes régionales d'une langue coexistent.
- **Une chaîne épuisée ne mène à rien.** L'appelant refuse — une 404, `null`, une exception à lui — plutôt que de
  servir une locale que personne n'a déclarée.
- **Un changement de langue est signalé, un changement de région ne l'est pas.** `leavesRequestedLanguage()` permet à
  l'application de le compter, ou de dire sur la page qu'elle s'affiche dans une autre langue.

Une chaîne résout ce qui est servi ; elle ne choisit pas la locale demandée. Ce choix — le `_locale` de la requête, une
négociation `Accept-Language`, une préférence de la personne — se fait avant, dans l'application.

## `DeclaredLocales`

Les locales que sert une application, à partir du tableau qu'elle déclare : chaque clé est une locale servie, chaque
valeur les locales essayées après elle, dans l'ordre.

| Membre | Renvoie |
|---|---|
| `DeclaredLocales::fromArray(array)` | les locales déclarées ; refuse une locale mal formée, une locale de repli qui n'est pas servie, des locales de repli qui ne forment pas une liste, une chaîne qui répète une locale |
| `all()` | les locales servies, dans l'ordre de leur déclaration |
| `isServed(Locale)` | si la locale est déclarée |
| `chainFor(Locale)` | sa `FallbackChain` ; refuse une locale qui n'est pas servie |

**Une locale de repli doit elle-même être servie** : une chaîne qui finit sur une locale que personne n'a déclarée
servirait ce que l'application ne publie pas.

## `Exception\InvalidLocaleDimension`

Une `InvalidArgumentException`, levée par chaque valeur refusée à sa construction. Son message nomme la valeur et
s'adresse au développeur ou à l'administrateur qui l'a déclarée ; un bundle qui lit une locale dans une adresse ou un
formulaire l'attrape et répond par une 404 ou une erreur de formulaire, ou lève sa propre exception avec celle-ci comme
précédente.
