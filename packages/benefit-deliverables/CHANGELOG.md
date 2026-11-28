# Changelog

All notable changes to `phil/benefit-deliverables` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Period`, `Benefit` and `Entitlement`: what each member is owed for a period, the tier left with the source; a
  member named by a stable identifier, never an email address.
- The port `Contract\EntitlementSource`, by pages ordered by member and for named members, so that the package
  depends on no module of subscriptions.
- `Checklist`: the lines of a period, `ChecklistItem` and `ChecklistPage`, every line or those `remaining()`, one
  benefit or all, by pages of 1 to 1000 members; `markDone()` one by one or by lot of up to 1000, members not owed
  the benefit left out, the first `Delivery` kept; `unmark()`; `DeliverableKey` and the port
  `Contract\DeliverableStore`.
- `Export\ShippingExports`: the CSV file of the deliverables still to be served of a benefit that ships, written by
  `phil/spreadsheet` with formulas guarded, holding the name, the lines of the address, the city, the region, the
  postal code, the country and the code of the benefit only, never the email address, identifier or tier of a member;
  members without an address left out and counted; 50,000 rows at most by default; `download()` through a signed
  link; `purge()` of the files expired.
- `Export\ExportRecord`, `Export\ExportResult` and the port `Contract\ExportJournal`: each export recorded, who asked
  for it, when, how many rows, until when its file is kept, when it was erased.
- `Export\DownloadLinks`: tokens signed by HMAC-SHA256 over the export and its expiry, under a key derived from the
  secret with HKDF, read strictly, compared in constant time; the same refusal whatever the reason.
- The port `Contract\ExportFileStore`, and `Export\LocalExportFileStore`: files `0600` in a directory, written whole
  or not at all.
- The port `Contract\DeliveryAddressProvider`, which reads a `phil/address` address per member.
- The port `Contract\DeliverableShipper`, with `Shipping\ShipmentRequest` and `Shipping\ShipmentReceipt`, for a
  carrier's API; no adapter.
- `Exception\InvalidDeliverable` and `Exception\ExportRefused`.
- The fuzz target `benefit-deliverables-token`, with its seeds.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
