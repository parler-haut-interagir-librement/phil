<!-- phil-dev source-sha256=8c129bd4fd43813419bc7f213ee9d01f8997b6fa7df28c493dd1d299b829f74f -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit sous `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau.

- `FractionTest` vérifie l'arithmétique exacte, chaque mode d'arrondi, et les étapes dont la fraction exacte ne tient
  plus, par rapport à des valeurs calculées en précision arbitraire.
- `UnitRegistryTest` vérifie les familles déclarées et chaque définition que le registre refuse.
- `MeasurementTest` vérifie les conversions par rapport aux définitions des unités, les températures et leurs
  différences, les unités réciproques et les répartitions.
- `Cldr/UnitPreferencesTest` et `Cldr/UnitFormatterTest` lisent les données de CLDR du paquet ; `Cldr/CldrImporterTest`
  importe un petit cldr-json dans un dossier temporaire qu'il supprime.
- `Property/ConversionRoundTripTest` vérifie des propriétés sur des valeurs tirées d'un générateur à germe fixe, de
  sorte qu'un échec se rejoue : une valeur convertie dans n'importe quelle unité de sa famille, puis reconvertie,
  redonne la valeur ; une conversion conserve l'ordre de deux valeurs (l'inverse pour une unité réciproque) ; et une
  somme est la même dans chaque unité.

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Les cibles de fuzzing

| Cible | Ce qu'elle lit | Germes |
|---|---|---|
| `measurement-parse` | `UnitRegistry::parse()` sur n'importe quels octets | `tests/Fuzz/corpus/measurement-parse` |
| `measurement-definitions` | `UnitRegistry::fromDefinitions()` sur n'importe quel JSON | `tests/Fuzz/corpus/measurement-definitions` |

Chaque lecture renvoie une mesure ou un registre, ou lance une `MeasurementException` : rien d'autre ne s'échappe, une
mesure relue à partir de son texte est la même, et chaque unité construite revient de son unité standard.

```sh
composer fuzz -- measurement-parse
```

## Les bancs d'essai

`tests/Benchmark/ConversionBench.php` mesure les chemins chauds d'un flux de produits : mille poids convertis dans
l'unité d'un canal, écrits dans une locale, ramenés à leur unité préférée, mille conversions dont la fraction exacte ne
tient plus, et un registre construit avec les unités d'une application.

```sh
composer bench -- packages/measurement/tests/Benchmark
```
