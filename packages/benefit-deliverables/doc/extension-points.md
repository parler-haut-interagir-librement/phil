# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports the application implements

| Port | What it answers | Where it is read |
|---|---|---|
| `Contract\EntitlementSource` | Who is owed which benefits for a period, by pages ordered by member, and for named members | `Checklist` |
| `Contract\DeliverableStore` | The deliveries marked, kept by period, benefit and member; the first delivery of each kept | `Checklist` |
| `Contract\DeliveryAddressProvider` | The postal address of each member who has one | `ShippingExports` |
| `Contract\ExportJournal` | The records of the exports, those expired, the erasure of their files | `ShippingExports` |
| `Contract\ExportFileStore` | Where the files wait: written whole or not at all, opened, erased | `ShippingExports`; `LocalExportFileStore` for a directory |
| `Contract\DeliverableShipper` | A deliverable handed to a carrier through its API | The application; no adapter shipped |

Each port that takes a list of members receives at most 1000; an implementation answers a page with one query.

## What an application may replace

| To | Do |
|---|---|
| Write another format than CSV | Pass another `Phil\Spreadsheet\Contract\SpreadsheetWriter` as the last argument of `ShippingExports` |
| Keep the files elsewhere than on a local disk | Implement `ExportFileStore` over private storage, never served as is |
| Make links last longer or shorter | Pass `$ttlSeconds`, 60 to 86,400 |
| Hold more or fewer rows | Pass `$maxRows`, 1 to 1,000,000 |
