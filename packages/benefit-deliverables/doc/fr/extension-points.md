<!-- phil-dev source-sha256=4db01959f11b65e9b9c520080aa2819e50ddc8a807ad13c659d48346f4dd30ae -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Les ports que l'application implémente

| Port | Ce qu'il répond | Où il est lu |
|---|---|---|
| `Contract\EntitlementSource` | Qui se voit dû quels avantages pour une période, par pages ordonnées par membre, et pour des membres nommés | `Checklist` |
| `Contract\DeliverableStore` | Les livraisons marquées, gardées par période, avantage et membre ; la première livraison de chacune est gardée | `Checklist` |
| `Contract\DeliveryAddressProvider` | L'adresse postale de chaque membre qui en a une | `ShippingExports` |
| `Contract\ExportJournal` | Les enregistrements des exports, ceux expirés, l'effacement de leurs fichiers | `ShippingExports` |
| `Contract\ExportFileStore` | Où les fichiers attendent : écrits en entier ou pas du tout, ouverts, effacés | `ShippingExports` ; `LocalExportFileStore` pour un répertoire |
| `Contract\DeliverableShipper` | Un élément à livrer confié à un transporteur par son API | L'application ; aucun adaptateur fourni |

Chaque port qui reçoit une liste de membres en reçoit 1000 au plus ; une implémentation répond à une page par une seule
requête.

## Ce qu'une application peut remplacer

| Pour | Faites |
|---|---|
| Écrire un autre format que CSV | Passez un autre `Phil\Spreadsheet\Contract\SpreadsheetWriter` comme dernier argument de `ShippingExports` |
| Garder les fichiers ailleurs que sur un disque local | Implémentez `ExportFileStore` sur un stockage privé, jamais servi tel quel |
| Allonger ou raccourcir la durée des liens | Passez `$ttlSeconds`, de 60 à 86 400 |
| Accepter plus ou moins de lignes | Passez `$maxRows`, de 1 à 1 000 000 |
