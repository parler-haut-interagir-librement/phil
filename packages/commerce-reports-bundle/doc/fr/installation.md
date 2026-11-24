<!-- phil-dev source-sha256=a6bd3914bd89f6875d9087da42e3097493fabb49a43576422e1fe27a9a82f0cd -->
# Installation

```sh
composer require phil/commerce-reports-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php`, après le bundle de rapports :

```php
Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
Phil\CommerceReportsBundle\PhilCommerceReportsBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, `phil/reporting-bundle`,
`phil/money` et `phil/commerce-bundle`. Les rapports apparaissent parmi ceux du bundle de rapports, sous les clés
`commerce_sales`, `commerce_sell_through` et `commerce_dead_stock` : ses périodes, ses formats, ses exports, ses
planifications et ses livraisons s'y appliquent comme à tout autre rapport.

## Brancher les ports

Les rapports lisent quatre ports. Le bundle crée un alias pour chacun de ceux que l'application laisse de côté :

| Port | Avec | Sans |
|---|---|---|
| `SoldLines` | `phil/commerce-pos-bundle` et les commandes du noyau de commerce : `PosSoldLines` | un adaptateur en mémoire vide |
| `StockFigures` | `phil/commerce-logistics-bundle` : `LogisticsStockFigures` | un adaptateur en mémoire vide |
| `ReturnedLines` | — | un adaptateur en mémoire vide |
| `StockedSkus` | — | un adaptateur en mémoire vide |

Aucun module ne liste encore `ReturnedLines` ni `StockedSkus` : créez-leur des alias vers des services de
l'application, sans quoi les ventes ne comptent aucun retour et les rapports de stock ne connaissent que les UGS
vendues pendant leur période.

```yaml
# config/services.yaml
services:
    Phil\CommerceReportsBundle\Application\Contract\ReturnedLines: '@App\Reports\ShopReturns'
    Phil\CommerceReportsBundle\Application\Contract\StockedSkus: '@App\Reports\ShopSkus'
```

Un alias que définit l'application est conservé ; [les points d'extension](extension-points.md) décrivent chaque port.

`DeadStockReport` lit l'heure dans `Psr\Clock\ClockInterface`, que FrameworkBundle enregistre avec `symfony/clock`.
