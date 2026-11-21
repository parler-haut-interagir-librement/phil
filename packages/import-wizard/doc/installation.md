# Installation

## Requirements

- PHP 8.5 or later, with `ext-filter` and `ext-mbstring`.
- No other package: the library reads and writes CSV itself, and writes records only through the target an
  application gives it.

## Installing

```sh
composer require phil/import-wizard
```

The library has no configuration and keeps no state between calls: `ImportPlanner` and `Importer` are plain objects,
safe to share in a long-running worker. A framework registers them as services, or the code builds them where it
needs them.

## A first import

An import takes four steps: read the file, plan it, show the plan, write it. The two last steps are often two
requests: the preview, then the confirmation.

```php
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\ImportWizardException;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;

$profile = LmsProfiles::organisationalUnits();

try {
    $plan = new ImportPlanner()->plan(CsvRowSource::fromFile($path), $profile, $unitsTarget);
} catch (MappingIncomplete $exception) {
    // Ask which header fills each of $exception->columns, then plan again with that mapping.
} catch (ImportWizardException $exception) {
    // The file is refused whole: show $exception->getMessage(), which names no value of the file.
}

foreach ($plan->preview() as $line) {
    // $line->line, $line->action (RowAction::Create, Update or Refuse) and, for a refused row, $line->errors,
    // each described by $plan->describe($error).
}
```

On confirmation, the file is planned again — the existing records may have changed in between — and written only if
the plan is the one the person saw:

```php
use Phil\ImportWizard\Import\Importer;

$plan = new ImportPlanner()->plan(CsvRowSource::fromFile($path), $profile, $unitsTarget);
$result = new Importer()->commit($plan, $unitsTarget, previewed: $fingerprintShownWithThePreview);
```

`$result->created`, `$result->updated` and `$result->rejections` say what was done. [The dry run](dry-run.md) goes
through each step, and [Extension points](extension-points.md) shows a target.
