# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Decide the issue order of each SKU otherwise than by configuration | Implement `Application\Contract\IssueOrderPolicy` and alias the interface to your service |
| Work out margins and commissions otherwise — through `phil/pricing`, say | Implement `Domain\Contract\MarginCalculator` and alias the interface to your service |
| Keep the ledger elsewhere than in Doctrine | Implement `Domain\Contract\LedgerRepository`; without DoctrineBundle, the bundle registers only the issue order, and the application declares `Ledger`, `ConsignmentStock`, `ShippedOrders` and the services of the consignors with its adapters |
| Split the stock of another stock module | Implement `Application\Contract\StockMovements` and `Application\Contract\ShippedUnits` and alias the interfaces to your services |
| Number the statements in a series of your own — per year, per shop | Implement `Application\Contract\StatementNumbering` and alias the interface to your service; take the number inside the transaction that keeps the statement |
| Keep agreements, statements and payouts elsewhere than in Doctrine | Implement `Domain\Contract\ConsignorRepository`, with a `StatementNumbering` of your own |
| Draw the statement otherwise | Override `templates/bundles/PhilCommerceConsignmentBundle/statement.html.twig`, or render `StatementView::data()` with a template of your own |

```yaml
# config/services.yaml
services:
    Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator: '@App\Consignment\PricingMargins'
```

The bundle tags no service and collects none.
