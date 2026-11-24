<!-- phil-dev source-sha256=12956cd8887af3d76050f55162a43f0a75ec42ead522b834883a10f0e1809895 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Décider autrement que par la configuration de l'ordre de sortie de chaque SKU | Implémentez `Application\Contract\IssueOrderPolicy` et créez un alias de l'interface vers votre service |
| Calculer autrement les marges et les commissions — par `phil/pricing`, par exemple | Implémentez `Domain\Contract\MarginCalculator` et créez un alias de l'interface vers votre service |
| Garder le grand livre ailleurs que dans Doctrine | Implémentez `Domain\Contract\LedgerRepository` ; sans DoctrineBundle, le bundle n'enregistre que l'ordre de sortie, et l'application déclare `Ledger`, `ConsignmentStock`, `ShippedOrders` et les services des consignataires avec ses adaptateurs |
| Répartir le stock d'un autre module de stock | Implémentez `Application\Contract\StockMovements` et `Application\Contract\ShippedUnits` et créez un alias des interfaces vers vos services |
| Numéroter les relevés dans une série à vous — par année, par boutique | Implémentez `Application\Contract\StatementNumbering` et créez un alias de l'interface vers votre service ; prenez le numéro dans la transaction qui garde le relevé |
| Garder les ententes, les relevés et les versements ailleurs que dans Doctrine | Implémentez `Domain\Contract\ConsignorRepository`, avec un `StatementNumbering` à vous |
| Dessiner le relevé autrement | Remplacez `templates/bundles/PhilCommerceConsignmentBundle/statement.html.twig`, ou produisez le rendu de `StatementView::data()` avec un gabarit à vous |

```yaml
# config/services.yaml
services:
    Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator: '@App\Consignment\PricingMargins'
```

Le bundle n'étiquette aucun service et n'en recueille aucun.
