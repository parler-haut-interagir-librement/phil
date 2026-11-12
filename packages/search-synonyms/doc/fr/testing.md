<!-- phil-dev source-sha256=9ae2640578f3e832dbef61edb83afbe4580339f98d93a39be821e31b1a455636 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Domaine | Ce qui est couvert |
|---|---|
| `SynonymSet`, `SynonymList`, `InvalidSynonyms`, `Violation` | Expressions normalisées et gardées une fois, chaque limite, langues, refus |
| `SynonymValidator` | Chevauchements et chaînes, une liste propre |
| `SolrSynonymsReader`, `SolrSynonymsWriter` | Commentaires, lignes vides, échappements, marque d'ordre des octets et fins de ligne, chaque refus avec sa ligne, les limites d'un fichier, une liste écrite puis relue |
| `JsonSynonyms` | Un document lu puis réécrit, chaque refus, les limites |
| `FileSynonymStore`, `InMemorySynonymStore` | Listes enregistrées et lues, langues, un fichier laissé de travers, un répertoire où l'on ne peut pas écrire |

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Fuzzing

Les deux lecteurs déclarent des cibles de fuzzing, exécutées sur des entrées que personne n'a écrites, à partir des
germes de `tests/Fuzz/corpus/<target>` : quoi qu'ils lisent, ils retournent une liste ou la refusent avec
`InvalidSynonyms`, et ne lancent rien d'autre. Depuis la racine du monodépôt :

```sh
composer fuzz -- solr-synonyms
composer fuzz -- json-synonyms
```
