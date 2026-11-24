# Configuration

The bundle's configuration lives under `phil_commerce_consignment`:

```yaml
# config/packages/phil_commerce_consignment.yaml
phil_commerce_consignment:
    issue_order: oldest_first
    issue_order_by_sku:
        BOOK-123: consigned_first
        ZINE-7: owned_first
    statement_number_prefix: ST-
    record_shipments: true
```

| Option | Default | What it does |
|---|---|---|
| `issue_order` | `oldest_first` | Which units of a SKU leave first when the shop holds some it bought and some on consignment: `owned_first`, `consigned_first` or `oldest_first`. |
| `issue_order_by_sku` | `[]` | Another issue order for the SKUs named, SKU → order. The SKUs are kept as they are written: `Book-1` is not `book-1`. |
| `statement_number_prefix` | `ST-` | The prefix of the statements' numbers, before eight digits — `ST-00000001` —: up to 24 letters, digits, dots, dashes, slashes or hashes, or nothing. See [Statements](statements.md#numbers). |
| `record_shipments` | `true` | Writes the sales of an order to the ledger when the logistics module announces `UnitsShipped`, in the transaction of the shipment. `false` leaves it to the application: `ShippedOrders::record()` or a `RecordShipment` message. See [With the logistics module](logistics.md#shipped-orders). |

The issue order applies to sales and to transfers between locations: see
[The ownership ledger](ledger.md#the-issue-order). A rule that the configuration cannot say — by product family, by the
contract of each consignor — is an `IssueOrderPolicy` of the application: see [Extension points](extension-points.md).

What the shop agreed with each consignor — how long their units stay, what happens to the unsold ones, how often they
receive a statement — is data, not configuration: see [Agreements](agreements.md).
