<!-- phil-dev source-sha256=67d2c42a39bf361088eba9f273dc63cf5604cd3147d13a183c28fb636e631824 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Les entités, `ResourceReference` | Chaque vérification et chaque borne ; les chemins, la profondeur, les déplacements et les cycles ; l'association des entités |
| `Taxonomy` avec `DoctrineTaxonomyRepository` | Sur SQLite en mémoire : étiquettes trouvées, attribuées, renommées, fusionnées ; catégories créées, traduites, déplacées, fusionnées avec une seule lecture de l'arbre et un seul *flush* ; noms lus dans leurs seules langues ; branches ; tout ce qui est conservé |
| `TagsType`, `CategoryType` | Texte et listes ; l'arbre en retrait dans une langue, ses traductions lues une fois ; choix donnés ; choix multiples ; l'option de locale |
| `PhilTaxonomyBundle` | Les services et l'association des entités |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite et le bundle : elle étiquette
et classe une ressource, choisit une catégorie en `fr_CA`, et lit l'aide en français.

```sh
vendor/bin/phpunit packages/taxonomy-bundle/tests
```

## Bancs d'essai

Les sujets phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/taxonomy-bundle/tests/Benchmark
```
