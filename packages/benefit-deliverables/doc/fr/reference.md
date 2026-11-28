<!-- phil-dev source-sha256=ddf5dd8713a10e28350176ba5687fa738d19a131a1622bc65763ca3a545c7b21 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Period` | `final readonly class` | Une période pour laquelle des avantages sont dus, nommée par l'application |
| `Benefit` | `final readonly class` | Ce qui est dû à chaque période : un code, un libellé, et s'il expédie un objet |
| `Entitlement` | `final readonly class` | Ce qui est dû à un membre pour une période |
| `DeliverableKey` | `final readonly class` | Un élément à livrer : une période, un avantage, un membre |
| `Delivery` | `final readonly class` | Un élément à livrer marqué fait, quand et par qui |
| `ChecklistItem` | `final readonly class` | Une ligne de la liste de contrôle, avec sa livraison une fois marquée |
| `ChecklistPage` | `final readonly class` | Les lignes d'une page de membres, et le membre après lequel la page suivante commence |
| `Checklist` | `final readonly class` | Les lignes d'une période, qui reste à servir, le marquage fait une à une ou par lot |
| `Contract\EntitlementSource` | interface | Qui se voit dû quoi pour une période |
| `Contract\DeliverableStore` | interface | Les livraisons marquées |
| `Contract\DeliveryAddressProvider` | interface | L'adresse postale de chaque membre |
| `Contract\ExportJournal` | interface | Les enregistrements des exports de livraison |
| `Contract\ExportFileStore` | interface | Où les fichiers d'export attendent |
| `Contract\DeliverableShipper` | interface | Un élément à livrer confié à un transporteur par son API |
| `Export\ShippingExports` | `final readonly class` | Écrit, remet et purge les exports de livraison |
| `Export\ExportRecord` | `final readonly class` | La trace d'un export : qui, quand, combien de lignes, jusqu'à quand, quand son fichier a été effacé |
| `Export\ExportResult` | `final readonly class` | Un export tout juste écrit et le jeton de son lien |
| `Export\DownloadLinks` | `final readonly class` | Signe et lit les jetons des liens de téléchargement |
| `Export\LocalExportFileStore` | `final readonly class` | Les fichiers d'export dans un répertoire local, `0600` |
| `Shipping\ShipmentRequest` | `final readonly class` | Ce dont un transporteur a besoin pour expédier un élément à livrer |
| `Shipping\ShipmentReceipt` | `final readonly class` | Le transporteur et le numéro de suivi d'un envoi |
| `Exception\InvalidDeliverable` | `final class`, `InvalidArgumentException` | Une valeur hors de ses bornes |
| `Exception\ExportRefused` | `final class`, `RuntimeException` | Un export qui ne peut être écrit ni remis |

`Member` et `Export\ExportCounts` sont internes.
