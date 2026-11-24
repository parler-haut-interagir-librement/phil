<!-- phil-dev source-sha256=da9a4f0d4b324647a9f3b7906a4cccc2d0f5f02110a04c1883c218156771a746 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Décider autrement que par la configuration de l'ordre de sortie de chaque SKU | Implémentez `Application\Contract\IssueOrderPolicy` et créez un alias de l'interface vers votre service |
| Calculer autrement les marges et les commissions — par `phil/pricing`, par exemple | Implémentez `Domain\Contract\MarginCalculator` et créez un alias de l'interface vers votre service |
| Garder le grand livre ailleurs que dans Doctrine | Implémentez `Domain\Contract\LedgerRepository` ; sans DoctrineBundle, le bundle n'enregistre que l'ordre de sortie, et l'application déclare `Ledger`, `ConsignmentStock`, `ShippedOrders` et les services des consignataires avec ses adaptateurs |
| Répartir le stock d'un autre module de stock | Implémentez `Application\Contract\StockMovements` et `Application\Contract\ShippedUnits` et créez un alias des interfaces vers vos services |
| Écrire plus tard les ventes des commandes expédiées, dans un worker | Réglez `record_shipments: false` et envoyez `RecordShipment` avec le numéro de la commande après chaque expédition |
| Numéroter les relevés dans une série à vous — par année, par boutique | Implémentez `Application\Contract\StatementNumbering` et créez un alias de l'interface vers votre service ; prenez le numéro dans la transaction qui garde le relevé |
| Garder les ententes, les relevés et les versements ailleurs que dans Doctrine | Implémentez `Domain\Contract\ConsignorRepository`, avec un `StatementNumbering` à vous |
| Dessiner le relevé autrement | Remplacez `templates/bundles/PhilCommerceConsignmentBundle/statement.html.twig`, ou produisez le rendu de `StatementView::data()` avec un gabarit à vous |

```yaml
# config/services.yaml
services:
    Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator: '@App\Consignment\PricingMargins'
```

Le bundle ne recueille aucun service. Il en étiquette un à lui, `UnitsShippedListener`, comme écouteur de `UnitsShipped`
sur le répartiteur d'événements de l'application.
