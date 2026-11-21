<!-- phil-dev source-sha256=872125dcdbd19aa80f18924720a6b82ec9c579c29689049545e7faad1299135a -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-filter` et `ext-mbstring`.
- Aucun autre paquet : la bibliothèque lit et écrit le CSV elle-même, et n'écrit des enregistrements qu'à travers la
  cible que lui donne une application.

## Installer

```sh
composer require phil/import-wizard
```

La bibliothèque n'a aucune configuration et ne garde aucun état entre deux appels : `ImportPlanner` et `Importer` sont
des objets simples, qu'un *worker* de longue durée peut partager sans risque. Un cadriciel les enregistre comme
services, ou le code les construit là où il en a besoin.

## Une première importation

Une importation se fait en quatre étapes : lire le fichier, le planifier, montrer le plan, l'écrire. Les deux
dernières étapes sont souvent deux requêtes : l'aperçu, puis la confirmation.

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

À la confirmation, le fichier est planifié de nouveau — les enregistrements existants ont pu changer entre-temps — et
n'est écrit que si le plan est celui que la personne a vu :

```php
use Phil\ImportWizard\Import\Importer;

$plan = new ImportPlanner()->plan(CsvRowSource::fromFile($path), $profile, $unitsTarget);
$result = new Importer()->commit($plan, $unitsTarget, previewed: $fingerprintShownWithThePreview);
```

`$result->created`, `$result->updated` et `$result->rejections` disent ce qui a été fait. [L'essai à blanc](dry-run.md)
parcourt chaque étape, et les [Points d'extension](extension-points.md) montrent une cible.
