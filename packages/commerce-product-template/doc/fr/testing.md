<!-- phil-dev source-sha256=55ce26605afe4588812a20f6cee5ff3014bb058125762d36b5f219d5afd5e258 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` construit un gabarit de chandail — versions, tailles et couleurs, une combinaison exclue, deux
faces — et un visuel en deux langues, et vérifie chaque produit de son plan : UGS, prix, canaux, illustration choisie
sur chaque zone, noms, attributs et photos. Chaque refus est vérifié avec son message, à chaque limite et au-delà. Les
gabarits et les visuels sont écrits en JSON et relus identiques.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-product-template/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## Cibles de fuzzing

`sku-pattern` analyse des patrons que personne n'a écrits et rend ceux qu'il accepte ; `template-array` lit des gabarits
à partir de JSON que personne n'a écrit. Chacune accepte l'entrée ou la refuse avec `InvalidDefinition`, et rien
d'autre :

```sh
composer fuzz
```

## Banc d'essai

`tests/Benchmark/VariantPlannerBench.php` planifie un gabarit de 3 versions, 18 tailles et 50 couleurs pour un visuel en
deux langues, soit 5 400 produits, et relit un gabarit à partir de son tableau.

## Tester du code qui planifie

Construisez de petits gabarits dans un jeu de données d'essai et comparez les UGS que donne un plan, plutôt que de
compter les produits :

```php
$skus = array_map(static fn (Variant $variant): string => $variant->sku, new VariantPlanner()->plan($template, $design));

self::assertSame(['NAQ001FOMBK', 'NAQ001FOMPK'], $skus);
```
