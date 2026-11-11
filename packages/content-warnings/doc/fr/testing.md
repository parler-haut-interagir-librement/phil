<!-- phil-dev source-sha256=3f1b836c61a31b7b1e031f89b34dabc0ee534e86e73eefbcd6040711fa3becee -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Key`, `LocalizedText` | Les clés et les locales valides et invalides, l'épuration, l'ordre de lecture des libellés, la fusion |
| `Catalogue`, `Category`, `Theme` | L'ordre, les recherches, les bornes, les refus |
| `CatalogueLoader`, `BaseCatalogue` | La construction, l'ajout, la fusion, le retrait, le remplacement, les fichiers et leurs bornes, chaque refus, les six langues de la liste de base |
| `ContentWarning`, `ContentWarnings` | Les notes, l'ordre, le remplacement sur place, les bornes, l'aller-retour et toutes les façons de refuser des avertissements stockés |
| `Severity`, `Display`, `Preference`, `WarningPreferences` | L'ordre, les seuils de sévérité, le thème avant la catégorie avant la valeur par défaut, l'aller-retour et les refus |
| `WarningMatcher`, `Decision` | L'absence d'avertissement, l'affichage le plus strict et ses avertissements, la comparaison sans catalogue |

Infection maintient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/content-warnings/tests
```

## Bancs d'essai

`WarningMatcherBench` mesure une décision pour zéro, trois et cent avertissements, ainsi que le chargement de la liste
de base. Depuis la racine du monodépôt :

```sh
composer bench -- packages/content-warnings/tests/Benchmark
```
