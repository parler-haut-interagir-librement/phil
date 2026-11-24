# Scope

## What the bundle does

- Splits the stock of each SKU by owner — the shop, each consignor — in layers per location, and writes every
  movement to a ledger.
- Receives goods line by line, bought at a unit cost or consigned on a share or a commission, against a purchase order
  or not, and adopts stock already on hand.
- Sells in a configurable issue order, takes returns back to the layers they left, transfers between locations and
  counts, of every owner or of one.
- Works out the margin of the shop's units and the commission of consigned ones, and what each sale owes each
  consignor.
- Writes the sales of the orders the logistics module ships, once.
- Keeps what the shop agreed with each consignor, version after version: the term, what happens to the unsold units,
  how often statements are issued.
- Issues numbered statements, read from the ledger and frozen, in CSV and as data for a template.
- Keeps what each consignor is owed and what was paid them, with partial payouts and reversals; moves no money.
- Lists the unsold units due, gives them back to their consignor — off the ledger and off the shelf — or has the shop
  buy them at the agreed cost.

## What it does not do

| Part | Where it stands |
|---|---|
| A statement sent to its consignor, by email or otherwise | The application sends it: the bundle gives the CSV and the data for a template. |
| A statement printed to PDF | The application prints the template's HTML with the converter it uses. |
| Statements issued on a schedule, in a worker | The application calls `ConsignorStatements::issue()` from its own scheduler. |
| Lots and expiry dates | Not planned for this bundle. |

## What it leaves to the application

- Screens: the bundle offers services; the application draws its own, for instance with the recipes of the PHIL UX kit,
  with CSRF on every action.
- Who a consignor is — a supplier, a contact, an account —: the bundle keeps their identifier, the terms of each
  receipt and the versions of their agreement.
- Paying the consignors: the bundle writes what was paid, never moves money.
- Who may read a statement, write a payout or give units back: the application's access rules.
- When a shipment is written: see [With the logistics module](logistics.md#shipped-orders).
