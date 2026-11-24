# Changelog

All notable changes to `phil/commerce-consignment-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- `Owner`, the shop or a consignor; `ConsignmentTerms`, a share per unit in `Money` or a commission in parts per
  million; `ReceiptLine`, bought at a unit cost or consigned on terms, and `Receipt`, up to 500 lines at one location,
  optionally against a purchase order.
- `StockLayer`, units of one SKU at one location of one owner on the same terms, versioned, with its origin receipt;
  `LedgerEntry`, every movement — received, adopted, sold, returned, transferred, counted —, with the unit price,
  revenue and cost of a sale or a return, instants in UTC.
- `IssueOrder`: owned first, consigned first or oldest first, the oldest receipt first within each group.
- `Ledger`: receipts, the split by owner, sales in the issue order, returns to the layers they left at the price they
  were sold at, transfers that keep owner, terms and instant, counts of every owner or of one; every operation checked
  before a unit moves.
- `Margin` and the `MarginCalculator` port, with `StandardMargins`: the price less the cost for the shop's units, the
  commission for consigned ones, a rate split with `Money::allocate()`; `Sale`, with what each consignor is owed.
- `ConsignmentStock`, which writes the ledger then moves the stock through the `StockMovements` port, adopts stock
  already on hand without exceeding it, and `ConfiguredIssueOrder` behind the `IssueOrderPolicy` port.
- `ConsignmentAgreement` and `ConsignorAgreements`: the versions of each consignor's agreement — term, `UnsoldPolicy`
  (`ReturnToConsignor`, `Extend` with its extensions, `BuyOut`) and `StatementFrequency` —, the first from any instant,
  each next one never before it is written, so that a change never rewrites the units received before it.
- `ConsignorStatement` and `ConsignorStatements`: statements of a consignor over a `StatementPeriod` in UTC, half-open,
  in one currency, read from the ledger's entries — units sold, returned by customers, bought out and given back, with
  revenue, share and commission summed and checked, and the units held with their age —, numbered by the
  `StatementNumbering` port and frozen once issued; `StatementLine` and `HeldUnits`; `StatementFrequency::lastClosedPeriod()`.
- `StatementCsv`, a statement as CSV with formulas made plain text, and `StatementView`, its data for the template
  `@PhilCommerceConsignment/statement.html.twig`, which an application overrides.
- `ConsignorPayout` and `ConsignorPayouts`: payouts written, never made, partial or whole and at most the balance,
  refused twice by a caller's idempotency key and a unique constraint, cancelled once by a reversal; `ConsignorBalance`
  with its `PayoutState`: due, partly paid, paid.
- `UnsoldStock`: the units due as the agreement at their receipt says (`DueUnits`); return slips that give units back,
  the oldest receipt first, off the ledger and off the shelf, refused whole before a unit moves; buy-outs at the agreed
  cost or the consignor's share, which change the owner of the units without moving them (`ReturnSlip`, `SlipLine`);
  the entry kinds `given_back`, `bought_out` and `bought_in`; `StockMovements::giveBack()`.
- `ShippedOrders`, which writes to the ledger what an order of `phil/commerce-bundle` shipped, once, at the price of
  its lines, through the `ShippedUnits` port; `UnitsShippedListener`, which writes them when the logistics module
  announces `UnitsShipped`, in the transaction of the shipment; `RecordShipment` and its handler for a worker.
- The adapters of `phil/commerce-logistics-bundle` 0.1.1: `LogisticsStockMovements` on `Stock` and `Purchasing` —
  count differences and units given back as adjustments, never below zero, returns of shipped orders as customer
  returns, receipts refused when a line disagrees with the owner or the unit cost of its purchase order line —, and
  `LogisticsShippedUnits` on its reservations.
- `DoctrineLedgerRepository` on two tables, which reads what is not flushed yet, with a consignor's entries, units held
  and what they are owed; `DoctrineConsignorRepository` on the tables of agreements, statements and payouts, and
  `DoctrineStatementNumbering`; `BinaryKeysListener` for the keys on MySQL and MariaDB; `InMemoryLedgerRepository`,
  `InMemoryConsignorRepository` and `InMemoryStatementNumbering`.
- `PhilCommerceConsignmentBundle` and its configuration `phil_commerce_consignment`: `issue_order`,
  `issue_order_by_sku`, `statement_number_prefix` and `record_shipments`; the Doctrine mapping; the Flex recipe.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
