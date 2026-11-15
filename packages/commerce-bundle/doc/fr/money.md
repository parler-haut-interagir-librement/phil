<!-- phil-dev source-sha256=6e0f8e07c19454819f6cba5735123514bf2041562da53b3351a9f3319f688781 -->
# Argent

Le bundle compte chaque montant avec `phil/money`, qu'apporte `phil/tax`, un prérequis du bundle :
`Phil\Money\Money`, un montant dans la plus petite unité de sa devise, et `Phil\Money\Currency`, un code ISO 4217 que
connaissent les données Unicode CLDR. Le bundle ne définit aucun type monétaire à lui ; la documentation de la
bibliothèque décrit les deux types, l'analyse des décimales, la répartition et ses exceptions.

Ce sur quoi s'appuie le bundle :

- **Des entiers en unités mineures.** `phil/tax` calcule les taxes en arithmétique entière sur `Money::$minorUnits`
  ([taxes.md](taxes.md)).
- **La devise fait partie de la valeur.** Des montants dans deux devises ne sont jamais additionnés, soustraits ni
  comparés : `Phil\Money\Exception\CurrencyMismatch` est levée, par la bibliothèque et par `phil/tax` quand une ligne
  de taxe mélange des devises. **Aucune conversion n'a lieu dans le bundle, jamais.**
- **Un montant négatif est légitime.** Une note de crédit annule une ligne de facture.
- **Un dépassement de capacité est refusé** avec `Phil\Money\Exception\InvalidAmount`, plutôt que changé en nombre à
  virgule flottante.

Les exceptions de `phil/money` implémentent `Phil\Money\Exception\MoneyException`, pas le `DomainError` de
`phil/ddd-bundle` : une application qui associe les erreurs de domaine à des réponses associe les deux.

Les formulaires, la validation, la mise en forme Twig et les colonnes Doctrine pour ces types sont dans
`phil/money-bundle`.

```php
use Phil\Money\Currency;
use Phil\Money\Money;

$cad = Currency::of('CAD');

$price = Money::ofDecimal('19.99', $cad);
$total = $price->add(Money::of(500, $cad));        // 24.99 CAD
$price->add(Money::of(100, Currency::of('USD'))); // CurrencyMismatch
```
