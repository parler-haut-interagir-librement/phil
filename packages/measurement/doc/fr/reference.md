<!-- phil-dev source-sha256=2636f830a336fb698ef3467dfd6de60a856b5c6873b25d0333e954fb128916de -->
# Référence

## `Phil\Measurement`

| Type | Rôle |
|---|---|
| `UnitRegistry` | Les familles et les unités, construites à partir de définitions en données : `standard()`, `fromDefinitions()`, `withDefinitions()` ; `unit()`, `family()`, `families()`, `has()`, `fromUcum()`, `measurement()`, `parse()`, `definitions()`. Immuable ; 64 familles et 1 024 unités au plus. |
| `UnitFamily` | Une famille : son `name`, son unité `standard`, ses `units` par code (l'unité standard en premier), sa catégorie CLDR `cldr` et sa famille de différences `difference` ; `has()`, `unitForCldr()`. |
| `Unit` | Une unité : `code`, `family`, `ucum`, `cldr`, `reciprocal`, `additive`, son unité de différences `difference` ; `factor()` et `offset()` écrits exactement, `isStandard()`. |
| `Measurement` | Une valeur exacte dans une unité : `of()`, `convertTo()`, `add()`, `subtract()`, `multiply()`, `negate()`, `compareTo()`, `isZero()`, `isNegative()`, `round()`, `toDecimal()`, `toFixedDecimal()`, `exactDecimal()`, `split()`, `splitRounded()`, `toString()`. Immuable. |
| `ChannelConversions` | L'unité dans laquelle chaque canal de vente affiche une famille : `convert()`, `unitFor()`, `channels()`. |
| `Fraction` | Interne : le nombre exact que contient une mesure. |

## `Phil\Measurement\Cldr`

| Type | Rôle |
|---|---|
| `UnitPreferences` | Les unités préférées d'une région pour un usage, d'après CLDR : `cldr()`, `preferred()`, `units()`. |
| `UnitFormatter` | Une mesure écrite dans une locale à partir des motifs de CLDR : `cldr()`, `format()`, `formatParts()`, `unitName()`. Garde les données de 64 locales au plus. |
| `UnitWidth` | `Long`, `Short` ou `Narrow`, les largeurs de CLDR. |
| `CldrImporter` | Interne : écrit `data/cldr/` à partir de cldr-json, lancé par `bin/import-cldr.php`. |

## `Phil\Measurement\Exception`

Chaque exception implémente `MeasurementException` ; chacune est une erreur de l'appelant, de ses données ou de ses
définitions.

| Type | Lancée pour |
|---|---|
| `InvalidMeasurement` | Un nombre décimal ou une mesure illisible, zéro dans une unité réciproque, un usage, une région, une locale ou un nombre de chiffres invalide. |
| `IncompatibleUnits` | Deux familles mélangées, deux températures additionnées, une consommation de carburant additionnée. |
| `UnknownUnit` | Un code, un code UCUM ou une famille que le registre ne contient pas, ou un code UCUM de deux familles sans sa famille. |
| `InvalidUnitDefinition` | Une définition que le registre refuse, l'unité d'un canal hors de sa famille, des données CLDR impossibles à importer. |
| `MeasurementOutOfRange` | Plus de 18 chiffres significatifs, ou une valeur dont la partie entière sort des entiers natifs. |
