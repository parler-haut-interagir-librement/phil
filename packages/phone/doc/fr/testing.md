<!-- phil-dev source-sha256=17010b569dbadb643aec7d1a09e2bf2c964d9f7c89f29ed6d1ea470317302d1d -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhoneNumber` | L'analyse dans 15 pays, les préfixes, les postes, les refus, les régions et les types, les quatre formats, le stockage |
| `Territories`, `Territory` | La recherche des plans, les préfixes nationaux et internationaux, les types, les premiers chiffres |
| `MetadataImporter` | Un jeu de données d'essai du XML de libphonenumber transformé en données, et les refus |

Infection exige que la bibliothèque tue 100 % des mutants.

```sh
vendor/bin/phpunit packages/phone/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine
du monodépôt :

```sh
composer bench -- packages/phone/tests/Benchmark
```
