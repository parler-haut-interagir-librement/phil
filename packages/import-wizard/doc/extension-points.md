# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\ImportTarget` | the application or the bundle that owns the records | writes the batches of one type, answers the lookups |
| `Contract\KeyLookup` | the same, through `ImportTarget` | says which natural keys of a type exist |
| `Contract\RowSource` | `CsvRowSource`, or an adapter | gives the header and the rows of a file, with their lines |
| `Contract\RowRule` | `DateOrder`, or the application | checks the cells of a row together |

## The target

A target writes one importable type. `existing()` returns, among the keys it is given, those of the records of a type
that exist — its own type for the creates and updates, the types of the references for them. `write()` creates and
updates the rows of a batch in their order, and returns the errors of the rows it refused, by line; their words never
repeat a value of the row.

```php
use Phil\ImportWizard\Contract\ImportTarget;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\RowAction;

final readonly class UnitTarget implements ImportTarget
{
    public function __construct(private UnitRepository $units)
    {
    }

    public function type(): string
    {
        return 'organisational-unit';
    }

    public function existing(string $type, array $keys): array
    {
        $codes = array_map(static fn (NaturalKey $key): string => $key->parts[0], $keys);
        $found = $this->units->existingCodes($codes); // one query for the chunk

        return array_values(array_filter($keys, static fn (NaturalKey $key): bool => \in_array($key->parts[0], $found, true)));
    }

    public function write(Batch $batch): array
    {
        $errors = [];

        foreach ($batch->rows as $row) {
            if ($this->units->isArchived($row->key->parts[0])) {
                $errors[$row->line] = [CellError::refusedByTarget('code', 'the unit is archived')];

                continue;
            }

            RowAction::Create === $row->action
                ? $this->units->create($row->values)
                : $this->units->update($row->key->parts[0], $row->values); // only the columns $row->has()
        }

        $this->units->flush();

        return $errors;
    }
}
```

A target writes a batch in one transaction when it can. The importer calls it from the request or the worker that
confirms the import; a long import is handed to a worker by the application, with the identifier of the stored file
and of the confirmed fingerprint, never the rows.

## Targets to write

These targets belong to the bundles that own the records, and are written there:

- **Courses** — the people (`LmsProfiles::people()`) and the enrolments (`LmsProfiles::enrolments()`): `existing()`
  answers the `person` and `course` keys; `write()` creates the accounts of the people and their enrolments, with the
  role and the dates.
- **Organisations** — the units (`LmsProfiles::organisationalUnits()`): `existing()` answers the `organisational-unit`
  keys; `write()` creates and moves the units, a parent always written in an earlier batch than its children; the
  `unit_code` of a person is answered by the same keys.

The import of the questions of an assessment is a later format, with its own profile and target.

## Sources

`CsvRowSource` reads CSV. An adapter reads any other format — a sheet of a workbook, rows from an API — by
implementing `RowSource`: `headers()` returns the trimmed header cells, `rows()` the `SourceRow`s after it, each with
the physical line the person will look for, blank rows included, in order. The planner reads the rows once.

## Rules

A rule implements `RowRule`: `check()` receives the values of a row by column — null for an empty cell — and returns
its `CellError`s; `name()`, `toArray()` and `fromArray()` serialise it with the profile. A profile read back with
`ImportProfile::fromArray($data, ruleTypes: [MyRule::class])` knows it.

## Options

- `Column`: type, required, header, aliases, length, choices, reference.
- `ImportProfile`: type, columns, natural key, rules, mapping.
- `CsvDialect`: delimiter, quote, encoding, byte order mark, formula guard.
- `SourceLimits`: bytes, rows, cells a row.
- `Importer`: the size of a batch.
