<!-- phil-dev source-sha256=44fb348f742435b5732c112a007ce41b8ed3230bad76f060b1e9231fbb903518 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhoneType`, `PhoneNumberTransformer` | Les régions, l'affichage, les champs vides, les refus |
| `ValidPhoneNumber` | Les numéros valides et invalides, les types, les pays, les messages et les codes |
| `PhoneNumberColumnType`, `PhoneExtension`, `PhilPhoneBundle` | Le stockage, les filtres, la configuration et les services |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle au
Canada : `PhoneBundleTest` stocke un numéro, valide en `fr_CA`, soumet le champ et produit le rendu des filtres. Quand
le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/phone-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine
du monodépôt :

```sh
composer bench -- packages/phone-bundle/tests/Benchmark
```
