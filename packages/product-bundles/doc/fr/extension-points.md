<!-- phil-dev source-sha256=ef7ee7f687bfb085a4698f8a402276af4f3225cfb5350805c0afd73d2051c6b6 -->
# Points d'extension

La bibliothèque est dans ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le journal des changements. Ils sont prévus pour être stables dès la version 1.0.

## Les ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\BundleRepository` | une table, avec ce que donne `ProductBundle::toArray()` | garde les lots par SKU ; trouve les lots qui contiennent un composant |
| `Contract\ComponentPrices` | un adaptateur vers le catalogue | donne le prix unitaire d'un SKU dans une devise, ou nul |
| `Contract\ComponentStock` | un adaptateur vers la logistique | donne les unités disponibles d'un SKU |
| `Contract\ComponentCosts` | un adaptateur vers l'évaluation des stocks | donne le coût unitaire d'un SKU maintenant, ou nul |

`Memory\InMemoryBundles` garde les lots dans un tableau, pour les tests et un processus unique.

## Le coût par la logistique

Une application qui utilise phil/commerce-logistics-bundle lit le coût unitaire de son port `InventoryValuation`, qui
suit la méthode choisie par la boutique :

```php
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\Money\Money;
use Phil\ProductBundles\Contract\ComponentCosts;

final readonly class ValuationCosts implements ComponentCosts
{
    public function __construct(private InventoryValuation $valuation)
    {
    }

    public function unitCost(string $sku): ?Money
    {
        return $this->valuation->unitCost($sku);
    }
}
```

## Le stock par la logistique

Les unités disponibles sont ce que phil/commerce-logistics-bundle peut encore réserver du SKU dans ses emplacements
ouverts :

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\ProductBundles\Contract\ComponentStock;

final readonly class LogisticsStock implements ComponentStock
{
    public function __construct(private Stock $stock)
    {
    }

    public function available(string $sku): int
    {
        return $this->stock->available($sku);
    }
}
```

La vente sort chaque `StockDraw` de `SoldBundle::draws()` par la logistique ; un retour remet chaque `StockDraw` de
`BundleRefund::restock`, que la logistique évalue au coût avec lequel les unités sont parties.

## Les prix par le catalogue

Le prix unitaire est celui auquel le catalogue vend le composant seul, dans la devise du canal ; retournez nul quand il
n'en a pas, et le lot n'est pas évalué.

## Stocker

Un magasin de données garde le tableau que donne `toArray()` — des scalaires et des nuls seulement — et reconstruit la
valeur avec `fromArray()`, qui refuse une ligne mal formée. Indexez le SKU du lot (unique), et les SKU des composants
pour `containing()`.
