# Profiles

An `ImportProfile` describes what a file of one importable type holds. It is a value: built in code, or read back from
the JSON it wrote.

```php
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Rule\DateOrder;

$profile = new ImportProfile('membership', [
    new Column('email', ColumnType::Email, required: true, aliases: ['courriel', 'e-mail']),
    new Column('club_code', required: true, maxLength: 32, reference: new Reference('club')),
    new Column('level', choices: ['bronze', 'silver', 'gold']),
    new Column('starts_on', ColumnType::Date),
    new Column('ends_on', ColumnType::Date),
], naturalKey: ['email', 'club_code'], rules: [new DateOrder('starts_on', 'ends_on')]);
```

## Types

The type of a column says how a cell is read. A cell is trimmed first; an empty cell is null.

| Type | Reads | Value |
|---|---|---|
| `Text` | anything | the text as written |
| `Integer` | at most 18 digits, an optional sign | `int` |
| `Decimal` | digits, an optional sign and fraction after `.` or `,` | the text, with `.`: no digit lost to a float |
| `Boolean` | `1`, `true`, `yes`, `y`, `oui`, `o` and `0`, `false`, `no`, `n`, `non`, in any case | `bool` |
| `Date` | `YYYY-MM-DD`, a date that exists | `DateTimeImmutable` at midnight UTC |
| `Email` | an address of at most 254 characters | the address in lower case |

## Checks

- `required`: an empty cell is refused.
- `maxLength`: a longer text is refused, counted in characters.
- `choices`: a text column takes one of the values listed, matched in any case and kept as listed.
- `reference`: the value is the natural key of a record of the type named — see below.

A column has a `header`, the one its template writes, its name by default, and up to 32 `aliases`, the other headers
it is recognised by.

## The natural key

The natural key names the required columns whose values identify a record. A row whose key a record already has
updates that record; any other row creates one. Two rows of a file with the same key are not both written: the second
is refused, naming the line of the first. A key of several columns is read in the order of the profile, its values as
text — a date as `YYYY-MM-DD`, yes and no as `1` and `0`.

An update carries the columns the file maps, an empty cell as null: the target clears that value. The columns the file
does not map are not in the row, and `PlannedRow::has()` tells them apart, so that the target leaves them as they are.

## References

A column with a `Reference` holds the natural key of a record of another type, or of the same type: a person's unit, a
unit's parent. The planner looks for that record:

1. in the file, when the reference is to the type of the profile — whatever the line, above or below;
2. among the records the target already holds, through `KeyLookup::existing()`.

A reference found nowhere refuses the row. So does a row that is its own parent, a cycle of references — each row of
the cycle is refused, naming the next —, and a row whose record of the file is refused, naming its line. A reference to
the profile's own type requires a natural key of one column.

## Rules across a row

A `RowRule` checks the cells of a row together, once each cell has been read without error. `DateOrder` refuses an end
date before its start date, when both are given. An application adds its own rules: see
[Extension points](extension-points.md#rules).

## The mapping

A `ColumnMapping` says which header of a file fills which column. The planner uses, in this order, the mapping it is
given, the one the profile keeps, and a guess for every required or optional column still unmapped among the headers
still free. The guess compares each header with the column's name, header and aliases, folded: lower case, the accents
of Latin letters removed, every character that is not a letter or a digit dropped — `Nom de famille`, `nom-de-famille`
and `NOMDEFAMILLE` read alike. A header fills one column; the first header that matches wins. A header mapped to no
column is ignored.

```php
$mapping = $plan->mapping->with('Mail pro', 'email')->without('Notes');
$plan = $planner->plan($source, $profile, $target, $mapping);
```

A required column that no header fills stops the plan with `MappingIncomplete`, whose `columns` lists them: the person
who imports picks their headers, and the file is planned again.

## Serialising

`toArray()` and `toJson()` write the profile — format, type, columns, natural key, rules and mapping —, and
`fromArray()` and `fromJson()` read it back the same, refusing data of the wrong shape with an `InvalidProfile` that
names the field. JSON is read up to 1 MiB and 16 levels deep. A rule an application adds is read back when its class
is passed:

```php
$json = $profile->withMapping($plan->mapping)->toJson();
$profile = ImportProfile::fromJson($json, ruleTypes: [ClubSeasonRule::class]);
```

A profile keeps a mapping of headers, never a value of a file.
