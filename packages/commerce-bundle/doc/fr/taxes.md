<!-- phil-dev source-sha256=0c014ae19242a8a9396d79071b4e00571f73fe195d111f879fb6d54cd784c216 -->
# Taxes

Les taux, les lignes, les ventilations et le calculateur à plusieurs taux sont les types de `phil/tax`, que le bundle
exige : des taux en parties par million, chaque taxe sur la même base, arrondie à la demie la plus éloignée de zéro
sur sa propre ligne, et des ventilations qu'une note de crédit annule exactement. La documentation de cette
bibliothèque les décrit. Le bundle ajoute le port qui dit quels taux s'appliquent à une vente, les taux qu'il lit
dans sa configuration, et le processeur qui taxe chaque ligne d'une commande.

| Type | Paquet | Rôle |
|---|---|---|
| `Phil\CommerceBundle\Application\Port\TaxRateResolver` | ce bundle | Port : les taux qui s'appliquent à une vente |
| `Phil\CommerceBundle\Application\ConfiguredTaxRates` | ce bundle | Les taux de `phil_commerce.tax_rates` ; le résolveur enregistré par défaut |
| `Phil\CommerceBundle\Application\TaxOrderProcessor` | ce bundle | Le processeur de commande qui taxe chaque ligne |
| `Phil\Tax\TaxRate` | `phil/tax` | Un code et une part en parties par million ; la taxe sur une base |
| `Phil\Tax\TaxBreakdown`, `Phil\Tax\TaxLine` | `phil/tax` | Les lignes sur une base, les totaux, l'annulation |
| `Phil\Tax\MultiRateTaxCalculator` | `phil/tax` | Chaque taux sur la même base, une ligne chacun |

## Exemple

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;

$rates = $resolver->ratesFor('digital', 'QC');  // GST 5 % and QST 9.975 %, from the application

$breakdown = new MultiRateTaxCalculator()->calculate(Money::of(1_999, Currency::of('CAD')), $rates);
$breakdown->totalTax()->minorUnits;              // 299: 100 and 199, each on 19.99
$credit = $breakdown->reversed();                // what a credit note carries
```

## Taxer une commande

`TaxOrderProcessor` s'exécute à chaque changement que fait un `OrderModifier` :

- chaque ligne est taxée sur son sous-total **avec ce qu'ajoutent ses options**, les ajustements `Option` de cette
  ligne ([options.md](options.md)), **après ses propres promotions**, les ajustements `Promotion` de cette ligne, et
  **après son calcul au prorata**, les ajustements `Proration` qu'un module d'abonnement met sur cette ligne ; les
  promotions sur toute la commande, les frais et la livraison ne font pas partie de la base d'une ligne ;
- les taux sont ceux que donne `TaxRateResolver` pour la catégorie de taxe de la ligne dans la zone de taxe de la
  commande ;
- un ajustement `Tax` par ligne et par taux, libellé avec le code du taux ;
- une commande sans zone de taxe ne porte aucun ajustement de taxe ;
- l'exécuter de nouveau remplace ses ajustements : il n'ajoute jamais une taxe deux fois.

## Les prix qui incluent les taxes

Une commande dont `taxIncluded()` est vrai — fixé par `OrderModifier::changeTaxIncluded()`, ou par la caisse pour
une session vendue ainsi — est vendue aux prix des étiquettes. Le processeur retire alors les taxes de chaque ligne
avec `MultiRateTaxCalculator::extract()` au lieu de les ajouter, et marque ses ajustements comme **neutres** :
affichés sur le reçu, exclus du total.

```php
$orders->changeTaxIncluded($order, true);
$order->total();        // 10.00, the price on the tag
$order->includedTax();  // 1.31: GST 0.44 and QST 0.87, taken out of it
```

Les mêmes prix unitaires servent dans les deux cas : basculer la commande recalcule ses taxes et rien d'autre.

Un processeur de promotions doit s'exécuter avant lui : son étiquette le nomme,
`['before' => TaxOrderProcessor::class]`.

## `TaxRateResolver`

Les seuils d'inscription, le lieu de fourniture et les règles de chaque territoire sont la décision de l'application,
souvent le travail d'un moteur de taxes externe.

```php
interface TaxRateResolver
{
    /** @return list<Phil\Tax\TaxRate> every rate that applies, each to the same base; an empty list when none does */
    public function ratesFor(string $taxCategory, string $zone): array;
}
```

Les deux arguments sont des noms que choisit l'application. `ConfiguredTaxRates` répond à partir de
`phil_commerce.tax_rates` ([configuration.md](configuration.md)) ; une application avec un moteur externe crée un
alias de son propre adaptateur vers le port, dont le contrat est dans [extension-points.md](extension-points.md).

## Ce que le bundle ne décide pas

- Si un vendeur doit percevoir des taxes (inscription, seuils des petits fournisseurs).
- Quel territoire taxe une vente (lieu de fourniture, emplacement de l'acheteur pour les ventes numériques).
- Les mentions légales d'un reçu (numéros d'inscription).
- Les taux en vigueur : les taux changent par la loi, et l'application ou son moteur de taxes les tient.
