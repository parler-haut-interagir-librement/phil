# Spelling numbers

`NumberWords` spells numbers in one locale. Build it with the locale and, if needed, `SpellingOptions` and the rules
of the language:

```php
use Phil\NumberWords\FrenchSpelling;
use Phil\NumberWords\NumberWords;
use Phil\NumberWords\SpellingOptions;

$words = new NumberWords('fr', new SpellingOptions(french: FrenchSpelling::Rectified1990));
```

The object keeps the ICU formatters it has built and nothing else: one instance can serve every request of a worker.

## Cardinals

`cardinal(int $number, ?Gender $gender = null)` gives the counting form without a gender, and the form that agrees with
a noun of that gender after it with one:

| Locale | `cardinal(21)` | `cardinal(21, Gender::Masculine)` | `cardinal(21, Gender::Feminine)` |
|---|---|---|---|
| `fr` | vingt et un | vingt et un | vingt et une |
| `es` | veintiuno | veintiún | veintiuna |
| `de` | einundzwanzig | einundzwanzig | einundzwanzig |
| `en` | twenty-one | twenty-one | twenty-one |

`cardinal(1)` is « eins » in German, `cardinal(1, Gender::Neuter)` is « ein ». A language without genders, such as
English, has one form for all of them. A gender the language has no rule set for, such as the neuter in French, throws
`MissingRule`.

Negative numbers are said with the language's word for minus (« moins vingt et un », « minus twenty-one »). ICU spells
numbers up to 10¹⁸ − 1 in most languages and writes digits past that; the library refuses digits in the result, in any
script, with `NumberOutOfRange`.

## Ordinals

`ordinal(int $number, ?Gender $gender = null, bool $plural = false)` takes zero or more; a negative number throws
`NumberOutOfRange`. Without a gender it gives the plain form of a language that has one (« twenty-first »,
« einundzwanzigste »), the masculine otherwise (« vingt et unième », « vigésimo primero »). `$plural` reaches the plural
forms of French, Spanish and Italian, and of German in recent ICU data (« premières », « terceras »); a language
without them throws `MissingRule`.

## Amounts

`amount(int|string $amount, string|CurrencyName $currency)` says an amount of money: its units, then its sub-units if
any, each number agreeing with the gender of its noun, the noun singular or plural for its count.

```php
new NumberWords('en')->amount('120.05', 'USD');   // one hundred twenty dollars and five cents
new NumberWords('es')->amount('21.01', 'GBP');    // veintiuna libras con un penique
new NumberWords('it')->amount('1000000', 'EUR');  // un milione di euro
new NumberWords('fr')->amount('-0.05', 'EUR');    // moins cinq centimes
```

The amount is an `int` or a decimal string with a point and at most 18 digits before it: `"120.05"`, `"-3"`, `"0.5"`.
Anything else — a comma, an exponent, a plus sign, a space — throws `InvalidAmount`, and so do more decimals than the
currency has, unless they are zeros (`"1.500"` is accepted, `"1.505"` is not): the library never rounds money. Zero
units are left out when there are sub-units; zero in all is said in units (« zéro euro », « zero dollars »).

The currency is an ISO 4217 code, in any case, that the language's rules name, or a `CurrencyName`:

| Codes named by the six languages | |
|---|---|
| `EUR`, `USD`, `CAD`, `AUD`, `NZD`, `GBP`, `CHF`, `JPY`, `MXN`, `BRL` | unit, sub-unit, their plurals and genders |

```php
use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Gender;

$crown = new CurrencyName('krone', 'kroner', Gender::Feminine, fractionDigits: 2, subunitOne: 'øre');
new NumberWords('en')->amount('2.05', $crown);    // two kroner and five øre
```

A `CurrencyName` with decimals needs a sub-unit; it has 0 to 4 decimals. A code the rules do not name throws
`MissingRule`; a language without rules, such as Dutch unless rules are passed, throws `UnsupportedLocale` for amounts
while its cardinals and ordinals work.

## Options

| `SpellingOptions` | Effect |
|---|---|
| `french: FrenchSpelling::Traditional` | « deux cent vingt et un », the default |
| `french: FrenchSpelling::Rectified1990` | « deux-cent-vingt-et-un », « trois millions deux-cent-mille » |
| `softHyphens: true` | keeps the soft hyphens (U+00AD) ICU puts inside long German, Italian and Spanish words, where a printed line may break |
| `verbose: true` | ICU's verbose rule sets: « one hundred and twenty »; a language without them throws `MissingRule` |

## What each language corrects

| Language | Corrections and grammar |
|---|---|
| French | `cents` and `quatre-vingts` take their `s` before `millions`, `milliards` and their kin (« deux cents millions »), ordinals left bare; hyphens in the chosen spelling; zero and one singular; `de`, or `d'` before a vowel, after a large noun (« un million d'euros »); `et`, `moins` |
| English | ICU's words; `and`, `minus` |
| Spanish | `de` after `millón` and its kin; `con`, `menos` |
| German | soft hyphens left out; no partitive (« eine Million Euro »); `und`, `minus` |
| Italian | `di` after `milione` and its kin; `e`, `meno` |
| Portuguese | `de` after `milhão`, `bilião` and their kin, the long scale of Portugal from ICU; `e`, `menos` |

Every language ICU spells works for cardinals and ordinals, with ICU's words and the soft hyphens left out. A locale ICU
cannot read, or whose language it does not spell — ICU would answer in another language —, throws `UnsupportedLocale`.
