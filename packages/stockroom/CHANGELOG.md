# Changelog

All notable changes to `phil/stockroom` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Container`, nested in a parent and labelled by a code, and `Item`, counted as a quantity or tracked as one unit
  (`Tracking`), with its home and its condition (`ItemCondition`: usable, needs repair, retired); `Identifier`, `Text`
  and `Quantity` bound every identifier — never an e-mail address —, name, note and quantity.
- `Place` (`PlaceKind`: container, event, holder) with a key a store keeps, `Holding`, and `Movement`
  (`MovementKind`: in, out, transfer, loss, return), its places checked against its kind and its moment kept in UTC.
- `Stockroom`: containers added, moved with all they hold and read as a path, at most 32 deep, loops refused; items
  with unique codes; `receive()`, `issue()` — from a container, or from the containers holding the item, its home
  first —, `transfer()`, `lose()` and `giveBack()`, each written to the journal as it changes the balances, never taking
  more than a place holds; tracked units in one place at a time; items under repair or retired kept in; contents,
  sources, histories and the movements of a reference, every listing bounded.
- `Deployments`: one load list per event (`LoadLine`, `LoadList`, at most 500 lines), `picking()` (`PickLine`),
  `deploy()` all or nothing or partial, finished later by the same call (`Deployment`, `Shortfall`), `outstanding()`,
  and `checkIn()`, which brings back what was counted, lists what is missing and writes it off when asked
  (`ReturnCheck`).
- `LoadListReader`, which reads a load list typed as text — quantity, item code, container — and writes one back, with
  the fuzz target `load-list`.
- The ports `Containers`, `Items`, `Balances`, `Journal` and `LoadLists`, their adapters in memory reset between
  requests, and `StockReader`, the read port of the stock for another package.
- `InvalidStock`, `UnknownStock` and `InsufficientStock`, under `StockroomException`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
