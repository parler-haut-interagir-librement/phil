# The dry run

Nothing is imported without a dry run: `Importer::commit()` takes an `ImportPlan`, and only `ImportPlanner::plan()`
makes one. The plan is what the person sees before confirming, and what is written after.

## Planning

```php
$plan = new ImportPlanner()->plan($source, $profile, $target);
```

The planner reads every row of the source, skipping the blank ones, and for each:

1. reads each mapped cell with its column — type, length, choices, required;
2. refuses a row with more cells than the header holds, unless the extra cells are blank, and a malformed row;
3. runs the profile's rules on a row whose cells were all read;
4. refuses a row whose natural key an earlier row of the file has;
5. resolves its references, in the file or through the target's lookup;
6. decides: update when the target has a record of that key, create otherwise.

The lookups are asked once per key, in chunks of at most 500 keys per call. The planner writes nothing and keeps no
state between calls.

## The plan

| Member | Holds |
|---|---|
| `rows` | the `PlannedRow`s to write, in the order they are written: every row after the rows of the file it refers to |
| `rejections` | the `RowRejection`s, in the order of the file: the line, the cells as written, the `CellError`s |
| `mapping` | the mapping used, to keep in the profile |
| `headers` | the headers of the file |
| `fingerprint` | a SHA-256 of the profile, the mapping, the headers, each row's line, action and values, and each refusal |
| `creates()`, `updates()`, `refusals()` | the counts |
| `preview()` | every row that is not blank, in the order of the file, as a `PreviewLine`: line, action, errors |
| `describe($error)` | the English sentence of an error, with the header of its column |

A `PlannedRow` holds its physical line, its action, its `NaturalKey`, the values of the mapped columns by name, the
line of the row of the file each of its references points to (`dependsOn`), and its cells as written.

## The reasons

A `CellError` names its column — null for the row as a whole —, its `Reason`, a detail that is never a value of the
file, and the line of the other row involved. The value of a `Reason` is a stable code an application translates;
`message()` is the English sentence the file of rejected rows carries.

| Code | When |
|---|---|
| `required` | an empty cell in a required column |
| `not-an-integer`, `not-a-decimal`, `not-a-boolean`, `not-a-date`, `not-an-email` | a cell that its type does not read |
| `too-long` | a text longer than its column allows; the detail gives the length |
| `not-allowed` | a value outside the choices; the detail lists them |
| `too-many-cells` | more cells than the header, not blank |
| `malformed-row` | text after a closing quote |
| `duplicate-key` | the key of an earlier row, whose line is given |
| `self-reference` | a row that refers to itself |
| `unknown-reference` | a reference found neither in the file nor by the lookup; the detail names the type |
| `reference-cycle` | a row of a cycle of references, naming the next row of the cycle |
| `reference-rejected` | a reference to a row of the file that is refused, whose line is given |
| `date-order` | an end date before its start date; the detail names the start column |
| `refused-by-target` | a row the target refused, with the target's own words |

## Writing

```php
$result = new Importer(batchSize: 500)->commit($plan, $target, previewed: $fingerprint);
```

The importer hands the rows to the target in batches of at most `batchSize`, in the order of the plan. A batch never
holds a row and a row it refers to: the batch closes before such a row, so that the target has written, and accepted,
the record referred to. A row the target refuses is reported with its errors, and a row that refers to it is refused
with `reference-rejected` without reaching the target.

Between the preview and the confirmation, the records the target holds may change. The application keeps the
fingerprint it showed, plans the file again on confirmation, and passes that fingerprint as `previewed`: a plan that
differs throws `PlanChanged` and nothing is written, so that the person previews again. A target of another type
throws `PlanChanged` too.

`ImportResult` holds the counts of records created and updated, the number of batches, and every rejection — the
plan's, the target's and those that followed — in the order of the file.

## The file of rejected rows

```php
$csv = RejectedRowsFile::csv($plan, $result->rejections);
```

The file holds, for each refused row, its line in the file imported (`import_line`), its errors (`import_errors`, each
`Header: reason`, separated by ` | `), then its cells under the headers of the file. The person corrects the cells and
imports the file again with the same profile: the two first columns map to no column and are ignored. Without
`$result->rejections`, the file holds the rejections of the plan.

```csv
import_line,import_errors,Email,First name,Last name
3,Email: is not an email address | First name: is required,nope,,Lee
5,Row: repeats the key of another row (line 2),ana@example.org,Ana,Lee
```

## Personal data

A row holds only what its file holds. The library logs nothing; the messages of its exceptions and the details of its
errors name a column, a line, a limit or a type, never the value of a cell. The fingerprint is a hash, kept beside the
preview instead of the plan. A rejected-rows file and a preview hold the file's data: the application gives them only
to the person who imported it.
