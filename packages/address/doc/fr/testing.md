<!-- phil-dev source-sha256=c9a4fc9092e9aef6694afdde6cde5234ebe42b31dc9e5c4a8b45cfcb696a487c -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Address`, `AddressArray`, `Countries` | Normalisation, refus, aller-retour |
| Formats et subdivisions | Formats, formats locaux, format générique, trois niveaux, noms dans leurs langues |
| `AddressValidator` | Champs obligatoires et inutilisés, codes postaux des pays et des régions, subdivisions par niveau |
| `AddressFormatter` | Canada, États-Unis, France, Japon dans ses deux formats, Brésil, Ukraine, Chine, Pérou, étiquettes postales |
| `DataImporter` | Un jeu de données d'essai tiré des données de commerceguys/addressing transformé en données, et les refus |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/address/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/address/tests/Benchmark
```
