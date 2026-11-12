# CSV

`CsvWriter` writes RFC 4180: UTF-8, each field quoted when it holds the separator, a quote or an end of line, a quote
doubled, every line ended by CRLF. It writes each row as it reads it.

```php
new CsvWriter(
    separator: ',',        // one byte, neither a quote nor an end of line: ',', ';' or "\t"
    byteOrderMark: true,   // the UTF-8 byte order mark first, which spreadsheets read as UTF-8
    guardFormulas: true,   // texts a spreadsheet would run are kept as text
);
```

A separator of another length, a quote or an end of line is refused with `ExportRefused`.

## Formula injection

A spreadsheet opening a CSV file runs a field that starts with `=`, `+`, `-` or `@` as a formula, and some run one that
starts with a tab or a carriage return; a formula can read other cells, open links or start programs. A field typed by
someone else — a title, a name, a comment — must not run.

`FormulaGuard::protect()` puts an apostrophe before a text that starts with one of these characters, or with one of them
after spaces. The spreadsheet shows the text, apostrophe hidden or shown, and runs nothing. A number written as text —
`-12`, `+3.5`, `-3,5`, `-1e5` — is left as it is, so a column of signed numbers stays numbers. The headings are guarded
too.

Only texts are guarded: an `int` or a `float` is written as a number. A file read back by a program rather than a
spreadsheet may turn the guard off with `guardFormulas: false`, when every field comes from the application itself.
