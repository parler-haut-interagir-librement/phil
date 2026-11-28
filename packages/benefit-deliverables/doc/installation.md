# Installation

```sh
composer require phil/benefit-deliverables
```

The package needs PHP 8.5 or later with the `mbstring` and `sodium` extensions, `phil/address` 0.1 for the postal
addresses and `phil/spreadsheet` 0.1 for the CSV file.

It registers nothing: an application builds `Checklist` and `ShippingExports` with its own implementations of the
ports, as services of its container for instance. See [Extension points](extension-points.md).

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
