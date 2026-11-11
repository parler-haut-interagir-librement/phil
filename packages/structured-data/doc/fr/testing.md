<!-- phil-dev source-sha256=249815aa38d0bc42bd5ac896e26dbe70e49699358fa98b93564e34940a0983fe -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Node`, `Reference`, `LanguageString`, `LanguageTag`, `Id` | Chaque sorte de valeur et sa forme écrite, les données relues, la fusion, la profondeur, les termes et identifiants refusés, les identifiants stables, les étiquettes de langue |
| `Graph`, `JsonLd` | La fusion par `@id`, les nœuds imbriqués remontés, les langues, la borne du nombre de nœuds, l'échappement de l'élément de script, les *nonces* |
| `JsonLdReader` | Les éléments lus, fusionnés et écartés, les documents de toutes les formes, les bornes d'une page |
| `Validator`, `Rules`, `Report` | Les propriétés requises et recommandées, les parents et les boucles, les valeurs de chaque format, les références et les nœuds sans type |
| Les fabriques | Chacun de leurs arguments, et un graphe de ces nœuds que le validateur accepte |
| `Schema` | Chaque méthode donne le nœud de sa fabrique, les avertissements laissés au validateur, et chaque sorte d'erreur refusée, dans un nœud imbriqué aussi |

```sh
vendor/bin/phpunit packages/structured-data/tests
```

## Fuzzing

`tests/Fuzz/jsonld-reader.php` donne au lecteur des entrées que personne n'a écrites, comme page et comme document
seul, puis valide et écrit ce qu'il a lu : rien d'autre que `InvalidStructuredData` ne doit en sortir, et aucun
avertissement. Ses germes se trouvent dans `tests/Fuzz/corpus/jsonld-reader`. Depuis la racine du monodépôt :

```sh
composer fuzz -- jsonld-reader
```

## Bancs d'essai

`JsonLdBench` assemble et écrit le graphe d'une page — un site, une organisation, une page et son fil d'Ariane — avec
0, 20 et 500 événements qui redonnent chacun l'organisation. Depuis la racine du monodépôt :

```sh
composer bench -- packages/structured-data/tests/Benchmark
```
