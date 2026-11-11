<!-- phil-dev source-sha256=888237dfdbcd168cc463f9c9d8edf15a79b68cae5cadd47530d2e8b72ba5aa0c -->
# Tests

## Les tests de la bibliothèque

`tests/Unit` contient `TaxRateTest`, `TaxBreakdownTest` et `MultiRateTaxCalculatorTest` : l'arrondi de part et d'autre
de la moitié, sur des bases de signe opposé et dans des devises à 0, 2 et 3 décimales ; la plus grande base taxée et la
suivante refusée ; les codes, parts et pourcentages acceptés et refusés, les pourcentages lus et réimprimés ; les
ventilations sur une même base, refusées sur une autre base, dans une autre devise ou avec un code deux fois ; les
montants fournis ; l'inversion ligne par ligne ; les taxes retirées d'un prix, la base arrondie de part et d'autre de
la moitié, un prix de signe opposé, le plus grand prix et le suivant refusé.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/tax/tests
```

Installée seule :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests de mutation maintiennent la bibliothèque au seuil d'une couche de domaine : chaque mutant de `src/` est tué.

## Tester du code qui taxe

- **Vérifiez les unités mineures**, ligne par ligne :
  `self::assertSame(199, $breakdown->line('QST')?->amount->minorUnits)`.
- **Gardez un cas dont les lignes s'arrondissent dans des sens opposés**, comme 19,99 à 5 % et 9,975 % : un calcul qui
  arrondit la somme des taux en une fois, ou qui les applique l'un sur l'autre, échoue.
- **Vérifiez qu'une inversion est exactement le signe opposé** :
  `$credit->totalWithTax()->equals($invoice->totalWithTax()->negate())`.
- La bibliothèque n'a ni horloge, ni conteneur, ni entrée-sortie : aucune doublure n'est nécessaire.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `calculate()` et `extract()` sur des paniers d'une à deux
cents lignes, avec un à trois taux. Depuis la racine du monodépôt :

```sh
composer bench -- packages/tax/tests/Benchmark
```
