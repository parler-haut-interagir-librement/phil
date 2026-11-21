# Reference

## `Phil\ImportWizard\Profile`

| Type | Role |
|---|---|
| `ImportProfile` | An importable type: columns, natural key, rules, mapping; `withMapping()`, `toJson()`, `fromJson()`. |
| `Column` | A column: name, type, required, header, aliases, length, choices, reference; `parse()` reads a cell. |
| `ColumnType` | `Text`, `Integer`, `Decimal`, `Boolean`, `Date`, `Email`; `parse()`, `text()`. |
| `Reference` | The type whose natural key a column holds. |
| `ColumnMapping` | Which header fills which column; `guess()`, `completedFor()`, `with()`, `without()`, `resolve()`. |
| `HeaderName` | Internal: a header folded for comparison. |
| `ProfileData` | Internal: the typed fields of a serialised profile. |

## `Phil\ImportWizard\Csv`

| Type | Role |
|---|---|
| `CsvRowSource` | The rows of a CSV text or file, read within limits, the header first. |
| `CsvReader` | The RFC 4180 reader, each record with its physical line. |
| `CsvDialect` | Delimiter, quote, encoding, byte order mark, formula guard; `detect()`. |
| `CsvWriter` | Writes rows as CSV, formulas guarded. |

## `Phil\ImportWizard\Source`

| Type | Role |
|---|---|
| `SourceRow` | A record: its physical line, its cells, whether it is malformed; `isBlank()`. |
| `SourceLimits` | The bytes, rows and cells a file may have. |

## `Phil\ImportWizard\Plan`

| Type | Role |
|---|---|
| `ImportPlanner` | The dry run: plans a source with a profile and a lookup. |
| `ImportPlan` | The rows to write in order, the rejections, the mapping, the fingerprint; `preview()`, `describe()`. |
| `PlannedRow` | A row to write: line, action, key, values, `dependsOn`, cells; `has()`, `value()`. |
| `RowRejection` | A refused row: line, cells, errors. |
| `CellError` | Why a cell or a row is refused: column, reason, detail, related line; `message()`. |
| `Reason` | The codes of the refusals; `message()`. |
| `RowAction` | `Create`, `Update`, `Refuse`. |
| `PreviewLine` | A line of the preview: line, action, errors. |
| `NaturalKey` | The values that identify a record, as text; `toString()`, `equals()`. |
| `Planning` | Internal: the working state of one dry run. |

## `Phil\ImportWizard\Import`

| Type | Role |
|---|---|
| `Importer` | Writes a plan through its target, in batches, the fingerprint checked. |
| `Batch` | Rows written together: type, number, rows; `creates()`, `updates()`. |
| `ImportResult` | Records created and updated, batches, every rejection. |

## `Phil\ImportWizard\Output`

| Type | Role |
|---|---|
| `RejectedRowsFile` | The refused rows as a CSV file to correct and import again. |
| `TemplateFile` | The header-only template of a profile, and its file name. |

## `Phil\ImportWizard\Lms`

| Type | Role |
|---|---|
| `LmsProfiles` | The profiles of the people, the enrolments and the organisational units; `all()`. |

## `Phil\ImportWizard\Rule`

| Type | Role |
|---|---|
| `DateOrder` | An end date never before its start date. |

## `Phil\ImportWizard\Contract`

| Type | Role |
|---|---|
| `ImportTarget` | The writer of one type: `type()`, `write()`, and the lookup. |
| `KeyLookup` | Which natural keys of a type exist. |
| `RowSource` | The header and the rows of a file. |
| `RowRule` | A check across the cells of a row, serialised with its profile. |

## `Phil\ImportWizard\Exception`

| Type | Role |
|---|---|
| `ImportWizardException` | Every exception of the library. |
| `SourceRefused` | A file refused whole. |
| `MappingIncomplete` | Required columns without a header; `columns`. |
| `InvalidProfile` | A profile, column, rule, dialect or limit that cannot be used, or serialised data of the wrong shape. |
| `PlanChanged` | A plan that is not the one previewed, or not of the target's type. |
