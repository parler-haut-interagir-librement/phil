# phil/import-wizard

**Import CSV files without surprises: a dry run previews each row as create, update or refuse, mappings replay, and
rejected rows come back to fix.**

phil/import-wizard is a plain PHP library. An application describes each importable type as an **`ImportProfile`**:
its columns and their types, the checks of each cell, the rules across a row, the **natural key** that tells an update
from a creation — an email address, a code, a person and a course —, and the references to other records. The profile
serialises to JSON and reads back the same, with the **mapping** of the headers the last file used, so that the next
file of the same shape is read the same way.

`CsvRowSource` reads the file within its limits — UTF-8 or a declared Windows encoding, the delimiter detected, quoted
cells over several lines — and keeps the **physical line** of every row, the line a spreadsheet shows. The mapping is
guessed from the headers, in English or French, with or without accents, and completed by what the person who imports
adjusts. **`ImportPlanner`** then makes the **dry run**: row by row, create, update or refuse, with the reason of each
refused cell, the duplicates of a key refused, and the references resolved to a record already known or to a row of
the same file, even further down — a tree of units is written parents first, whatever the order of the file. Nothing
is written before the person has seen that preview.

**`Importer`** writes the plan through an **`ImportTarget`**, the port the application or the bundle that owns the
records implements: the library never writes to a database. Batches never hold a row and the row it refers to, a row
whose parent the target refused is refused in turn, and a plan made again since the preview is not written unless its
fingerprint is the same. **`RejectedRowsFile`** gives back the refused rows as a CSV file, each with its line and its
errors, to correct and import again; **`TemplateFile`** gives the header-only template of a type.

`LmsProfiles` ships the three CSV files of a learning platform: the people, their enrolments in courses, and the tree
of the organisational units.

## Status

| Part | State |
|---|---|
| `ImportProfile`, `Column`, `ColumnType`, `Reference`, `ColumnMapping`: profiles serialised and replayed | Implemented and tested |
| `CsvRowSource`, `CsvReader`, `CsvDialect`, `SourceLimits`: CSV read within limits, physical lines kept | Implemented and tested |
| `ImportPlanner`, `ImportPlan`: the dry run, natural keys, references within the file and outside it | Implemented and tested |
| `Importer`, `Batch`, `ImportTarget`: the plan written in batches through the target, fingerprint checked | Implemented and tested |
| `RejectedRowsFile`, `TemplateFile`, `CsvWriter`: the files to correct and the templates, formulas guarded | Implemented and tested |
| `LmsProfiles`: people, enrolments, organisational units | Implemented and tested |
| `DateOrder` and the `RowRule` port | Implemented and tested |
| Reading XLSX and ODS, importing the questions of an assessment | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and a first import from start to end. |
| [Profiles](doc/profiles.md) | Columns, types, checks, natural keys, references, rules, the mapping, serialising. |
| [Formats](doc/formats.md) | The CSV read and written, and the three formats of a learning platform. |
| [The dry run](doc/dry-run.md) | The plan, the preview, the reasons, writing it, and the files of rejected rows. |
| [Extension points](doc/extension-points.md) | The target, the lookup, the sources and the rules an application implements. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/import-wizard
```

```php
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Output\RejectedRowsFile;
use Phil\ImportWizard\Plan\ImportPlanner;

$profile = LmsProfiles::people();
$plan = new ImportPlanner()->plan(CsvRowSource::fromFile($uploadedPath), $profile, $peopleTarget);

// Show $plan->preview() — create, update or refuse, line by line — and keep $plan->fingerprint with the confirmation.

$result = new Importer()->commit($plan, $peopleTarget, $confirmedFingerprint);
$toCorrect = RejectedRowsFile::csv($plan, $result->rejections);
$profileJson = $profile->withMapping($plan->mapping)->toJson(); // replayed on the next file
```

## Requirements

PHP 8.5 or later, with `ext-filter` and `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
