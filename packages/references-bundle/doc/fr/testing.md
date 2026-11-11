<!-- phil-dev source-sha256=737df46f76a8fa8f8be10dab80d8c6a507cb4f317792b3341db7b8ce3f0cb658 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ResourceReference`, `Usage`, `StillReferenced` | Chaque vérification et chaque borne ; le message avec plus de trois sources |
| `References` | Rafraîchir et oublier, chaque lien que produit un collecteur, la vérification avant suppression avec des sources qui partent, les liens rompus |
| `DbalUsageRepository`, `UsageTable`, l'écouteur de schéma | Remplacement avec doublons, usages, usages de plusieurs cibles dans leur ordre et par paquets, cibles, oubli par genre et identifiant ; la table |
| `ReferenceTrackingListener`, `CheckReferencesCommand` | Rafraîchir et oublier dans l'ordre des *flush* ; suppressions refusées et permises ; la commande |
| `PhilReferencesBundle` | Services, autoconfiguration, configuration |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, des pages dont les blocs JSON
pointent vers des images, ainsi qu'un collecteur et une cible : `ReferencesBundleTest` fait des *flush*, liste les
usages et les liens rompus, et voit annulé un *flush* qui supprime une image utilisée.

```sh
vendor/bin/phpunit packages/references-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/references-bundle/tests/Benchmark
```
