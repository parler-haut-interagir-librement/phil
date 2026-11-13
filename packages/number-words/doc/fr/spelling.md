<!-- phil-dev source-sha256=b15d3ebf08e3a6315ad16e26931c28112a80d5e77287f58f47bced342bd957a0 -->
# Épeler des nombres

`NumberWords` épelle des nombres dans une locale. Construisez-le avec la locale et, au besoin, `SpellingOptions` et les
règles de la langue :

```php
use Phil\NumberWords\FrenchSpelling;
use Phil\NumberWords\NumberWords;
use Phil\NumberWords\SpellingOptions;

$words = new NumberWords('fr', new SpellingOptions(french: FrenchSpelling::Rectified1990));
```

L'objet garde les formateurs ICU qu'il a construits et rien d'autre : une même instance peut servir toutes les requêtes
d'un *worker*.

## Cardinaux

`cardinal(int $number, ?Gender $gender = null)` donne la forme de comptage sans genre, et, avec un genre, la forme qui
s'accorde avec un nom de ce genre placé après :

| Locale | `cardinal(21)` | `cardinal(21, Gender::Masculine)` | `cardinal(21, Gender::Feminine)` |
|---|---|---|---|
| `fr` | vingt et un | vingt et un | vingt et une |
| `es` | veintiuno | veintiún | veintiuna |
| `de` | einundzwanzig | einundzwanzig | einundzwanzig |
| `en` | twenty-one | twenty-one | twenty-one |

`cardinal(1)` donne « eins » en allemand, `cardinal(1, Gender::Neuter)` donne « ein ». Une langue sans genres, comme
l'anglais, a une seule forme pour tous. Un genre pour lequel la langue n'a pas de jeu de règles, comme le neutre en
français, lève `MissingRule`.

Les nombres négatifs se disent avec le mot de la langue pour moins (« moins vingt et un », « minus twenty-one »). ICU
épelle les nombres jusqu'à 10¹⁸ − 1 dans la plupart des langues et écrit des chiffres au-delà ; la bibliothèque refuse
les chiffres dans le résultat, dans toute écriture, avec `NumberOutOfRange`.

## Ordinaux

`ordinal(int $number, ?Gender $gender = null, bool $plural = false)` accepte zéro ou plus ; un nombre négatif lève
`NumberOutOfRange`. Sans genre, il donne la forme simple d'une langue qui en a une (« twenty-first »,
« einundzwanzigste »), le masculin sinon (« vingt et unième », « vigésimo primero »). `$plural` donne accès aux formes
du pluriel du français, de l'espagnol et de l'italien, et de l'allemand dans les données récentes d'ICU
(« premières », « terceras ») ; une langue qui n'en a pas lève `MissingRule`.

## Montants

`amount(int|string $amount, string|CurrencyName $currency)` dit un montant d'argent : ses unités, puis ses sous-unités
s'il y en a, chaque nombre s'accordant avec le genre de son nom, le nom au singulier ou au pluriel selon son compte.

```php
new NumberWords('en')->amount('120.05', 'USD');   // one hundred twenty dollars and five cents
new NumberWords('es')->amount('21.01', 'GBP');    // veintiuna libras con un penique
new NumberWords('it')->amount('1000000', 'EUR');  // un milione di euro
new NumberWords('fr')->amount('-0.05', 'EUR');    // moins cinq centimes
```

Le montant est un `int` ou une chaîne décimale avec un point et au plus 18 chiffres avant lui : `"120.05"`, `"-3"`,
`"0.5"`. Tout le reste — une virgule, un exposant, un signe plus, une espace — lève `InvalidAmount`, tout comme plus de
décimales que n'en a la devise, sauf si ce sont des zéros (`"1.500"` est accepté, `"1.505"` ne l'est pas) : la
bibliothèque n'arrondit jamais un montant d'argent. Les unités à zéro sont omises quand il y a des sous-unités ; un
zéro en tout se dit en unités (« zéro euro », « zero dollars »).

La devise est un code ISO 4217, en majuscules ou en minuscules, que les règles de la langue nomment, ou un
`CurrencyName` :

| Codes nommés par les six langues | |
|---|---|
| `EUR`, `USD`, `CAD`, `AUD`, `NZD`, `GBP`, `CHF`, `JPY`, `MXN`, `BRL` | unité, sous-unité, leurs pluriels et leurs genres |

```php
use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Gender;

$crown = new CurrencyName('krone', 'kroner', Gender::Feminine, fractionDigits: 2, subunitOne: 'øre');
new NumberWords('en')->amount('2.05', $crown);    // two kroner and five øre
```

Un `CurrencyName` avec des décimales exige une sous-unité ; il a de 0 à 4 décimales. Un code que les règles ne nomment
pas lève `MissingRule` ; une langue sans règles, comme le néerlandais tant qu'on ne lui en passe pas, lève
`UnsupportedLocale` pour les montants, alors que ses cardinaux et ses ordinaux fonctionnent.

## Options

| `SpellingOptions` | Effet |
|---|---|
| `french: FrenchSpelling::Traditional` | « deux cent vingt et un », la valeur par défaut |
| `french: FrenchSpelling::Rectified1990` | « deux-cent-vingt-et-un », « trois millions deux-cent-mille » |
| `softHyphens: true` | garde les traits d'union conditionnels (U+00AD) qu'ICU place dans les longs mots allemands, italiens et espagnols, où une ligne imprimée peut se couper |
| `verbose: true` | les jeux de règles détaillés d'ICU : « one hundred and twenty » ; une langue qui n'en a pas lève `MissingRule` |

## Ce que corrige chaque langue

| Langue | Corrections et grammaire |
|---|---|
| Français | `cents` et `quatre-vingts` prennent leur `s` devant `millions`, `milliards` et leurs semblables (« deux cents millions »), les ordinaux restant nus ; traits d'union selon l'orthographe choisie ; zéro et un au singulier ; `de`, ou `d'` devant une voyelle, après un grand nom (« un million d'euros ») ; `et`, `moins` |
| Anglais | Les mots d'ICU ; `and`, `minus` |
| Espagnol | `de` après `millón` et ses semblables ; `con`, `menos` |
| Allemand | traits d'union conditionnels omis ; aucun partitif (« eine Million Euro ») ; `und`, `minus` |
| Italien | `di` après `milione` et ses semblables ; `e`, `meno` |
| Portugais | `de` après `milhão`, `bilião` et leurs semblables, l'échelle longue du Portugal tirée d'ICU ; `e`, `menos` |

Toute langue qu'ICU épelle fonctionne pour les cardinaux et les ordinaux, avec les mots d'ICU et les traits d'union
conditionnels omis. Une locale qu'ICU ne sait pas lire, ou dont il n'épelle pas la langue — ICU répondrait dans une
autre langue —, lève `UnsupportedLocale`.
