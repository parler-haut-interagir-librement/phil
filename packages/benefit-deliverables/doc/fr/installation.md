<!-- phil-dev source-sha256=2a234d88471ed0213ad63b981bdd97f0d297fc44c3a03eb69ef8effbca2574a0 -->
# Installation

```sh
composer require phil/benefit-deliverables
```

Le paquet exige PHP 8.5 ou une version plus récente avec les extensions `mbstring` et `sodium`, `phil/address` 0.1 pour
les adresses postales et `phil/spreadsheet` 0.1 pour le fichier CSV.

Il n'enregistre rien : une application construit `Checklist` et `ShippingExports` avec ses propres implémentations des
ports, par exemple comme services de son conteneur. Voir [Points d'extension](extension-points.md).

```php
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\Export\DownloadLinks;
use Phil\BenefitDeliverables\Export\LocalExportFileStore;
use Phil\BenefitDeliverables\Export\ShippingExports;

$checklist = new Checklist($entitlements, $deliveries);
$exports = new ShippingExports(
    $checklist,
    $addresses,
    $journal,
    new LocalExportFileStore('/var/lib/app/exports'),   // outside the web root
    new DownloadLinks($secret),                         // 32 bytes or more, from the application's secrets
);
```
