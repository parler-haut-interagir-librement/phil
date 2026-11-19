# Configuration

Every choice is an argument of `LiblouisTranslator`:

```php
use Phil\BrailleExport\Contraction;
use Phil\BrailleExportLiblouis\LiblouisTables;
use Phil\BrailleExportLiblouis\LiblouisTranslator;

$translator = new LiblouisTranslator(
    tables: LiblouisTables::defaults()->with('de', Contraction::Contracted, 'de-g2.ctb'),
    binary: '/usr/bin/lou_translate',
    timeout: 60.0,
    tablePath: '/srv/braille/tables',
);
```

| Argument | Default | Meaning |
|---|---|---|
| `tables` | `LiblouisTables::defaults()` | The table of each language and contraction. |
| `binary` | `lou_translate` | The program: a name found on the `PATH`, or a path. |
| `timeout` | 30 | The seconds one batch may take. |
| `maxInputBytes` | 4 MiB | The most bytes of text one batch may send. |
| `maxOutputBytes` | 32 MiB | The most bytes the program may write for one batch, its error output included. |
| `displayTable` | `unicode.dis` | The display table, which must write Unicode braille. |
| `tablePath` | none | The directories liblouis looks for tables in, given to it as `LOUIS_TABLEPATH`; its own directory without it. |

## Tables

A table is the file name of a liblouis table, or several separated by commas, never a path. `LiblouisTables` maps a
language to its tables for `uncontracted` and `contracted`:

```php
$tables = new LiblouisTables([
    'en' => ['uncontracted' => 'en-ueb-g1.ctb', 'contracted' => 'en-ueb-g2.ctb'],
    'en-US' => ['contracted' => 'en-us-g2.ctb'],
    'fr' => ['uncontracted' => 'fr-bfu-comp6.utb', 'contracted' => 'fr-bfu-g2.ctb'],
]);
```

| Language | Uncontracted | Contracted |
|---|---|---|
| `en`: Unified English Braille | `en-ueb-g1.ctb` | `en-ueb-g2.ctb` |
| `fr`: Code braille français uniformisé | `fr-bfu-comp6.utb` | `fr-bfu-g2.ctb` |

These are the defaults. A language tag is read in lower case, `_` as `-`: `fr-CA` takes the tables of `fr-ca` when
there are some, else those of `fr`. `with()` gives a copy with one table set. The names are checked: letters, digits,
`.`, `_` and `-`, at most eight tables of 128 characters each.

The tables a language may take are listed in the table directory of liblouis, one file per table, each starting with
its language and its grade in its comments.

## The display table

The program writes each cell as its display table says. The translator reads Unicode braille, so the display table is
`unicode.dis` unless the tables are kept somewhere with another table that writes the same characters. A line that is
not braille stops the batch with a `LiblouisFailed` that names the display table.

## Long texts

The program reads one line per text. A text longer than 1,000 bytes is sent in pieces cut at the last space that
fits, else between two characters, and its braille is joined again with a blank cell.
