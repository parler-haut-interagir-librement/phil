# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Period` | `final readonly class` | A period benefits are owed for, named by the application |
| `Benefit` | `final readonly class` | Something owed each period: a code, a label, whether it ships an object |
| `Entitlement` | `final readonly class` | What one member is owed for a period |
| `DeliverableKey` | `final readonly class` | One deliverable: a period, a benefit, a member |
| `Delivery` | `final readonly class` | A deliverable marked done, when and by whom |
| `ChecklistItem` | `final readonly class` | A line of the checklist, with its delivery once marked |
| `ChecklistPage` | `final readonly class` | The lines of a page of members, and the member the next page starts after |
| `Checklist` | `final readonly class` | The lines of a period, who is still to be served, marking done one by one or by lot |
| `Contract\EntitlementSource` | interface | Who is owed what for a period |
| `Contract\DeliverableStore` | interface | The deliveries marked |
| `Contract\DeliveryAddressProvider` | interface | The postal address of each member |
| `Contract\ExportJournal` | interface | The records of the shipping exports |
| `Contract\ExportFileStore` | interface | Where the export files wait |
| `Contract\DeliverableShipper` | interface | A deliverable handed to a carrier through its API |
| `Export\ShippingExports` | `final readonly class` | Writes, hands out and purges the shipping exports |
| `Export\ExportRecord` | `final readonly class` | The trace of an export: who, when, how many rows, until when, when its file was erased |
| `Export\ExportResult` | `final readonly class` | An export just written and the token of its link |
| `Export\DownloadLinks` | `final readonly class` | Signs and reads the tokens of the download links |
| `Export\LocalExportFileStore` | `final readonly class` | The export files in a local directory, `0600` |
| `Shipping\ShipmentRequest` | `final readonly class` | What a carrier needs to ship one deliverable |
| `Shipping\ShipmentReceipt` | `final readonly class` | The carrier and the tracking number of a shipment |
| `Exception\InvalidDeliverable` | `final class`, `InvalidArgumentException` | A value out of its bounds |
| `Exception\ExportRefused` | `final class`, `RuntimeException` | An export that cannot be written or handed out |

`Member` and `Export\ExportCounts` are internal.
