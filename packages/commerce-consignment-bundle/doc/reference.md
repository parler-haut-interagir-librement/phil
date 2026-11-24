# Reference

Every public type of the package, with its role.

## Services

| Type | Role |
|---|---|
| `Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle` | The bundle: its configuration `phil_commerce_consignment` and its services. |
| `Application\ConsignmentStock` | Receives, adopts, sells, takes back, transfers and counts, in the ledger and in the stock; the split by owner. |
| `Application\ShippedOrders` | Writes to the ledger what an order shipped, once, at the price of its lines. |
| `Application\ConfiguredIssueOrder` | The issue order of the configuration, for every SKU and per SKU. |
| `Application\ConsignorAgreements` | The versions of each consignor's agreement, checked against the past; the version in effect at an instant. |
| `Application\ConsignorStatements` | Issues the statements of a period, read from the ledger; the last period over at the agreement's frequency. |
| `Application\ConsignorPayouts` | Writes payouts and their reversals; the balance of a consignor. |
| `Application\UnsoldStock` | The units due; units given back to their consignor; units bought by the shop. |
| `Application\Export\StatementCsv` | A statement as CSV. |
| `Application\Export\StatementView` | A statement as plain data for a template, and the name of the bundle's template. |
| `Domain\Service\Ledger` | The layers and their entries: receipts, sales, returns, transfers, counts, the split. |
| `Domain\Service\StandardMargins` | The default margins and commissions. |

## Values

| Type | Role |
|---|---|
| `Domain\ValueObject\Owner` | The shop or a consignor. |
| `Domain\ValueObject\ConsignmentTerms` | A share per unit or a commission in parts per million. |
| `Domain\ValueObject\Receipt`, `ReceiptLine` | Goods received, line by line bought or consigned. |
| `Domain\ValueObject\Sale`, `SaleLine` | Units sold or returned, by layer, with their margin and what each consignor is owed. |
| `Domain\ValueObject\Margin` | Revenue, cost or consignor's share, margin or commission. |
| `Domain\ValueObject\Movement` | Units a transfer put in a layer. |
| `Domain\ValueObject\OwnerQuantity` | The units of a SKU one owner holds. |
| `Domain\ValueObject\StatementPeriod` | Two instants in UTC, the start included, the end excluded. |
| `Domain\ValueObject\StatementLine` | One entry as a statement shows it. |
| `Domain\ValueObject\HeldUnits` | Units of one layer still in the shop at an instant, with their age. |
| `Domain\ValueObject\ConsignorBalance` | What the shop owes a consignor, what it paid, the balance and its state. |
| `Domain\ValueObject\DueUnits` | Units of one layer whose consignment is over, and what to do with them. |
| `Domain\ValueObject\ReturnSlip`, `SlipLine` | Units given back or bought out, by layer, with what a buy-out owes. |
| `Domain\Consignor\UnsoldPolicy` | `ReturnToConsignor`, `Extend`, `BuyOut`. |
| `Domain\Consignor\StatementFrequency` | `Weekly`, `Monthly`, `Quarterly`; the last period over at an instant. |
| `Domain\Consignor\PayoutState` | `Due`, `PartlyPaid`, `Paid`. |
| `Domain\Stock\IssueOrder` | `OwnedFirst`, `ConsignedFirst`, `OldestFirst`. |
| `Domain\Stock\EntryKind` | Why a layer moved. |
| `Application\ShippedQuantity` | Units of a SKU an order shipped from one location. |
| `Application\Message\RecordShipment` | An order's number, for a worker. |

## Entities and ports

| Type | Role |
|---|---|
| `Domain\Model\StockLayer` | Units of one SKU at one location of one owner, on the same terms; versioned. |
| `Domain\Model\LedgerEntry` | One movement of a layer, never changed. |
| `Domain\Model\ConsignmentAgreement` | One version of a consignor's agreement, never changed. |
| `Domain\Model\ConsignorStatement` | A numbered statement, frozen when issued. |
| `Domain\Model\ConsignorPayout` | A payout or its reversal, never changed. |
| `Domain\Contract\LedgerRepository` | Finds and stores layers and entries; a consignor's entries, units held and what they are owed. |
| `Domain\Contract\ConsignorRepository` | Finds and stores agreements, statements and payouts. |
| `Application\Contract\StatementNumbering` | Gives the next statement number. |
| `Domain\Contract\MarginCalculator` | Works out a margin or a commission. |
| `Application\Contract\IssueOrderPolicy` | The issue order of a SKU. |
| `Application\Contract\StockMovements` | Moves the stock another module keeps. |
| `Application\Contract\ShippedUnits` | What an order shipped, by SKU and location. |
| `Domain\Exception\ConsignmentRefused` | An operation refused before anything moved. |
| `Domain\Exception\InvalidValue` | A value the ledger cannot hold. |

## Adapters

| Type | Role |
|---|---|
| `Infrastructure\Doctrine\DoctrineLedgerRepository` | The ledger in two tables; what is not flushed yet counts. |
| `Infrastructure\Doctrine\DoctrineConsignorRepository` | Agreements, statements and payouts in three tables. |
| `Infrastructure\Doctrine\DoctrineStatementNumbering` | The statements' series, counted in their table. |
| `Infrastructure\Doctrine\BinaryKeysListener` | The binary collation of the keys on MySQL and MariaDB. |
| `Infrastructure\Memory\InMemoryLedgerRepository` | The ledger in memory, for tests. |
| `Infrastructure\Memory\InMemoryConsignorRepository` | Agreements, statements and payouts in memory, for tests. |
| `Infrastructure\Memory\InMemoryStatementNumbering` | A series counted in memory, for tests. |
| `Infrastructure\Logistics\LogisticsStockMovements` | The stock of `phil/commerce-logistics-bundle`. |
| `Infrastructure\Logistics\LogisticsShippedUnits` | The shipped units, read from its reservations. |
| `Infrastructure\Logistics\UnitsShippedListener` | Writes the sales of an order when the logistics module announces `UnitsShipped`. |
| `Infrastructure\Messenger\RecordShipmentHandler` | Handles `RecordShipment`. |

## Template

| Name | Role |
|---|---|
| `@PhilCommerceConsignment/statement.html.twig` | A statement, from `StatementView::data()`; overridden in `templates/bundles/PhilCommerceConsignmentBundle/`. |
