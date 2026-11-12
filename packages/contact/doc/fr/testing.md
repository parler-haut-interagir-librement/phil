<!-- phil-dev source-sha256=ff842e6d3a196e07204cabbafde46db09e534ea888380942ae603432cf01eb74 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Email`, `ContactCard` | La normalisation, les refus, les noms affichés, les fiches vides, les rôles téléphoniques |
| `PersonName`, `NameConventions` | De vrais noms de chaque culture, écrits et triés — français, néerlandais des Pays-Bas et de Belgique, allemand, espagnol, portugais, italien, anglais, chinois, japonais, coréen, hongrois, vietnamien, islandais —, les ordres donnés, les refus, les limites |
| `VCard`, `SchemaOrg` | Chaque propriété, l'échappement, le repli entre deux caractères, les personnes et les organisations |
| `ContactCardArray` | L'aller-retour, les clés absentes, les noms structurés, et chaque façon dont une fiche enregistrée est refusée |
| `JSContactWriter`, `JSContactReader` | Chaque propriété écrite, l'aller-retour, chaque avis, chaque refus, les limites |

Infection maintient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/contact/tests
```

## Fuzzing

La cible de fuzzing `jscontact-reader` donne au lecteur des textes que personne n'a écrits, à partir des germes de
`tests/Fuzz/corpus/jscontact-reader` : quoi qu'il lise, la fiche qu'il donne s'écrit en un JSContact qui se relit en
le même JSContact, ou le texte est refusé par l'une des exceptions de la bibliothèque. Depuis la racine du monodépôt :

```sh
composer fuzz -- jscontact-reader
```

## Bancs d'essai

Les sujets phpbench sous `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles : une vCard écrite pour
une fiche et pour un annuaire, une fiche JSContact lue et écrite. Depuis la racine du monodépôt :

```sh
composer bench -- packages/contact/tests/Benchmark
```
