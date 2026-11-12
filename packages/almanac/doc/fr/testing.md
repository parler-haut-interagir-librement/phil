<!-- phil-dev source-sha256=5c371af4a4e56ed39ab8d369fbf56efc1d913e4be530e918c95b3a0f48912572 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Saisons, Lune, Soleil | Les exemples 25.a, 27.a et 49.a de Meeus; les saisons et les phases de 2026 telles que les publie l'United States Naval Observatory, à la minute près; les bords d'une recherche; les plages; des valeurs fixées au millième de seconde sur toute la plage |
| Lever, coucher, crépuscules | Les exemples 25.a et 28.b de Meeus; le lever et le coucher de Montréal, de New York, de Londres, de Sydney et de Reykjavík aux solstices tels que les donnent des tables publiées, à la minute près; les crépuscules de Montréal; le Soleil de minuit, la nuit polaire et un crépuscule qui dure toute la nuit; `date_sun_info()` de PHP à 90 secondes près sur une année à trois latitudes; les plages |
| Éclipses | Les exemples 54.a et 54.b de Meeus; chaque éclipse solaire et lunaire de 2022 à 2026 comparée au canon de la NASA (instant, genre, γ, magnitude); les phases de l'éclipse lunaire du 7 septembre 2025; les 224 éclipses solaires du XXIe siècle, dont 68 totales; les bornes d'une période et les plages |
| ΔT | Chaque polynôme à ses bords, la parabole à long terme |
| Pâques | Des dates connues, et chaque année de 1583 à 4099 comparée à l'algorithme de Gauss, occidentale et orthodoxe |
| Nouvel An chinois | Chaque année de 1985 à 2050 sauf quelques-unes, dont 2034 après le mois intercalaire de 2033; la règle du mois intercalaire; les termes principaux; les animaux et les éléments |
| Règles | Chaque forme lue, réécrite et trouvée dans une année, celles des éclipses aussi; les cas limites (cinquièmes jours de la semaine, 29 février, un 31 en décembre, des phases le 1er janvier); chaque refus |
| Définitions et almanach | Les durées, un `until` le même jour, l'année suivante ou jamais, les refus, des périodes qui traversent les années et les fuseaux horaires, les étiquettes, l'ordre |

```sh
vendor/bin/phpunit packages/almanac/tests
```

Infection tient la bibliothèque à 100 % de mutants tués. Les quelques mutants qu'aucune année de la plage ne peut
distinguer sont nommés dans le `phil-dev.php` du paquet, avec la raison.

## Fuzzing

`RuleParser` est une cible de fuzzing, `rule-parser` : sur n'importe quel texte, il donne une règle dont le texte se relit
en la même règle, ou une exception de l'almanach, jamais autre chose ni un avertissement. Ses germes sont dans
`tests/Fuzz/corpus/rule-parser`.

```sh
composer fuzz -- rule-parser --budget=60
```

## Bancs d'essai

Les sujets phpbench sous `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/almanac/tests/Benchmark
```
