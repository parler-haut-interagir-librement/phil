<!-- phil-dev source-sha256=c43597f037aa49009f663d9767fe73a53f26d4781fe200908de6ae801dace716 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Indexing`, `Reader` | Ce que chaque valeur répond à chaque lecteur, les valeurs que permet chaque lecteur, les directives aux robots, la plus stricte de plusieurs valeurs, les valeurs conservées |
| `IndexingPolicy` | Une fiche qui répond pour elle-même, un objet sans le contrat, un filtre qui garde l'ordre et les clés, la plus stricte des fiches d'une page, l'attribut de requête |

```sh
vendor/bin/phpunit packages/indexing-consent/tests
```
