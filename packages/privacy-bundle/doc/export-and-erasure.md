# Subject export and erasure

**State:** `SubjectExport`, `SubjectErasure`, the value objects and the exceptions are implemented and tested.
`PersonalDataExporter` and `PersonalDataEraser` are **ports**: the application implements them. **The bundle wires
the rest**: it autoconfigures both ports with the tags `phil_privacy.exporter` and `phil_privacy.eraser`, registers
`SubjectExport` and `SubjectErasure` as public services over those tags, and the commands `phil:privacy:export` and
`phil:privacy:erase`.

The model is one contract per module. Each part of the application that holds personal information answers
for its own records; two application services assemble the answers for one person.

## The person: `DataSubject`

`DataSubject::fromString(string $value)` — the opaque identifier every module indexes its records on (a UUID,
an OIDC `sub`, a customer number). The bundle never parses it. It refuses only what no module can index on:

- an empty string;
- more than 255 bytes (`DataSubject::MAX_LENGTH`);
- any whitespace or control character — how an identifier copied from an e-mail arrives with a trailing
  newline and matches nothing.

`$value`, `equals(DataSubject $other): bool`, and `Stringable` (`(string) $subject`).

## The module: `ModuleName`

`ModuleName::fromString(string $value)` — the key of a module's section in an export and the name its erasure
report and failure are attributed to. Lowercase letter first, then lowercase letters, digits, `_`, `.` or `-`;
at most 64 characters (`billing`, `crm.contacts`).

## Export

### Port: `PersonalDataExporter`

```php
public function module(): ModuleName;

/** @return array<string, mixed> JSON-encodable; [] when the module holds nothing on the person */
public function export(DataSubject $subject): array;
```

Build the export **from the read models the module already queries**, so it stays correct as the model
changes instead of becoming a second view maintained by hand.

### Service: `SubjectExport`

```php
$export = new SubjectExport([$billingExporter, $crmExporter]);
$sections = $export->export(DataSubject::fromString($userId));
// ['billing' => [...], 'crm' => [...]]
```

- `__construct(iterable<PersonalDataExporter> $exporters)` — throws `MisconfiguredPrivacy::duplicateModule()`
  when two exporters share a module name.
- `export(DataSubject $subject): array<string, array<string, mixed>>` — one section per module, **sorted by
  module name** (`ksort`, string order), so two exports of the same data are the same document. A module that
  holds nothing still has its section, empty: the answer shows the module was asked. With no exporter the
  result is `[]`.

Encoding (JSON, CSV, archive), delivery, identity verification and deadlines are the application's.

## Erasure

### Port: `PersonalDataEraser`

```php
public function module(): ModuleName;

public function erase(DataSubject $subject): ErasureReport;
```

**Must be idempotent.** A failed erasure is retried, and an erasure is replayed after a backup restore: running
it again on an already erased person must succeed and report the same effects.

### Value: `ErasureReport`

`ErasureReport::of(string $module, array<string, ErasureEffect> $effects)` — what one module did, store by store.

- `$module` (`ModuleName`) and `$effects` (by store name).
- **An empty report is refused**: it cannot be told apart from a module that forgot a store. A module that
  erased nothing still reports each store with `Survives` or `AwaitsExpiry` where that is the answer.
- A blank store name is refused.

`ErasureEffect` cases: `Deleted`, `Pseudonymised` (the record survives without anything naming the person),
`Redacted` (some fields blanked or replaced), `AwaitsExpiry` (nothing removed now; the retention period runs
out), `Survives` (kept on purpose, under a legal obligation or because removing it would undo the request).

### Service: `SubjectErasure`

```php
$erasure = new SubjectErasure([$billingEraser, $crmEraser]);

try {
    $reports = $erasure->erase($subject);          // list<ErasureReport>, sorted by module name
} catch (ErasureIncomplete $incomplete) {
    $incomplete->completed;                        // list<ErasureReport> of the modules that succeeded
    $incomplete->failures;                         // array<string, Throwable> by module name
    $incomplete->getPrevious();                    // the first failure
    // record, alert, retry later: every eraser runs again, which is why they are idempotent
}
```

- `__construct(iterable<PersonalDataEraser> $erasers)` — throws `MisconfiguredPrivacy::duplicateModule()` on a
  duplicate module name. Erasers run in module name order.
- **One failing module does not stop the others.** Every eraser runs; if any failed, `ErasureIncomplete` carries
  what succeeded and what did not, so a person is never told an erasure completed that did not. Stopping at the
  first failure would leave later modules untouched for no reason.
- **A report filed under another module's name is a failure** of the eraser that returned it
  (`UnexpectedValueException` in `$failures`): it would otherwise mark a module done that never ran.
- Any `Throwable` from an eraser is caught and recorded, errors included.

`ErasureIncomplete` message: `Erasure failed in module(s) <names>; retry it, erasers are idempotent.`

## Wiring in Symfony

A service implementing `PersonalDataExporter` is tagged `phil_privacy.exporter`, one implementing
`PersonalDataEraser` `phil_privacy.eraser`, by autoconfiguration; `SubjectExport` and `SubjectErasure` take every
tagged module and are public. Which modules take part is decided by which adapters the application registers.
Duplicate module names surface when the service is first instantiated, so cover each service with a test that
builds it from the container.

The bundle takes part as one module of its own when `email_tracking.enabled`: `email_tracking` exports the member's
choice about tracked emails — its version, `opens`, `clicks` and its date in UTC — and its erasure deletes it
([email-tracking.md](email-tracking.md)).

## From the console

```bash
bin/console phil:privacy:export 0192f3c4-… --output=answer.json
bin/console phil:privacy:erase 0192f3c4-… --confirm
```

`phil:privacy:export` prints the answer to an access request as JSON, one section per module, or writes it to
`--output`. The file holds every personal record of the person: it is created readable and writable by its owner
only (`0600`), whatever the umask; an existing file is refused unless `--force` is given, and then narrowed to
`0600` too. A write that fails or falls short exits with a failure and says so, never `The answer … is in` over an
empty or missing file. `phil:privacy:erase` does nothing without `--confirm`; with it, it prints what each store of each
module did — `module: store, effect` —, and when a module fails, the modules that completed, the one that failed and
why, and exits with a failure: running it again retries, erasers being idempotent. A subject identifier the domain
refuses exits as invalid.

## The register of processing

```bash
bin/console phil:privacy:register --format=markdown > docs/register-of-processing.md
bin/console phil:privacy:register --strict          # fails while the register has gaps, for the CI
```

The register has two parts, and names what keeps it from being complete.

- **Its activities** are the rules of the retention schedule that hold personal information, or that a field names,
  in schedule order: the rule, its store and categories, its purpose, recipients, processors and location
  ([retention.md](retention.md#processing-and-processor)), its period, and the `#[PersonalData]` fields that name it.
  Without a schedule there is none.
- **Its fields** are every `#[PersonalData]` of the classes under `phil_privacy.register.classes` and of every
  Doctrine entity, one line per field — a field declared on a parent once, under its class —, ordered by category,
  class and property: the category, the retention rule it names and, with a schedule, that rule's store, period,
  mechanism and answer to erasure. A field whose rule the schedule does not hold shows the rule's name alone.
- **Its gaps**, one sentence each: an activity that states no purpose, one that states no location, and, with a
  schedule, a field that names a rule the schedule does not hold.

`ProcessingRegister::activities()`, `entries()` and `gaps()` give the three parts; each accepts any iterable of class
names, a generator included. The Markdown output has a section per part, the gaps only when there are some; a cell
keeps its pipes escaped and its lines joined, so that a purpose cannot break the table. `--format=json` gives an object
with `activities`, `fields` and `gaps`. With `--strict`, the command exits with a failure while there are gaps, and says
how many on the error output.

## The archive of an access answer

A person's export is one ZIP archive: a `README.txt` that says what it is, when it was made and what each file holds,
then one JSON file per module, `<module>.json`, in module order. A module that keeps nothing about the person is an
empty object, and the README says so. The archive is written by `ZipFile`, without the zip extension: raw deflate when
zlib is there, stored otherwise, names in UTF-8. It refuses a name that could escape the folder it is opened in — an
absolute path, `..`, `.`, a backslash, a control character —, a name given twice, and what the plain format cannot
hold, beyond 65,535 files or 4 GiB.

```php
$zip = $container->get(ExportPackage::class)->zip($subjectExport->export($subject));
```

The README comes from an `ExportReadme`: with Twig, `@PhilPrivacy/export/README.txt.twig` in the language of the
request, its texts in the `phil_privacy` domain; without it, `PlainExportReadme`, in English. Override the template to
speak of your site and of who to contact.

A member's download from their preferences, the answer to a request made without an account
([requests-without-an-account.md](requests-without-an-account.md)) and the export written before an account is erased
are all this archive.

## What belongs to the application

How a request that does not come through the site is received and recorded; the legal deadlines; the request register;
and what each module erases and how. The requests of people without an account who write through the site are the
bundle's, once turned on.
