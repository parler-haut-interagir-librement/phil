# Security

## The formula guard

A spreadsheet runs as a formula a cell whose text starts with `=`, `+`, `-` or `@`, and some also one starting with a
tab or a carriage return: a text a member typed into a survey can then compute, fetch an address or run a command when
the export is opened (OWASP, CSV injection). The writers keep such texts as texts unless
`WriteOptions::$guardFormulas` is turned off:

- **CSV** has no types: the text gets an apostrophe in front, which spreadsheets read as "this is a text" — `=1+1`
  is written `'=1+1`. The apostrophe is part of the file: a program that reads the CSV back sees it.
- **XLSX** writes every text as an inline string, which a spreadsheet never runs; a text that would run once edited is
  marked with the format's quote prefix, which keeps it a text in the spreadsheet, and the value is unchanged.
- **ODS** writes every text as a string cell, which a spreadsheet never runs: the format has nothing to change.

The heading row is guarded like the data. Turn the guard off only for texts no person typed — codes the application
made, for instance.

## Reading files nobody checked

The readers are made for the files people upload:

- **Sizes.** The size of the file is checked before anything is read. An archive with more entries than the limit is
  refused when it is opened. Each part is read through a byte budget that counts the bytes as they are uncompressed:
  the size an archive declares for an entry is checked too, but a ZIP bomb that lies about it is stopped by the count.
- **Rows, columns and cells.** The highest row, the widest row and the cells of the whole read are bounded, the
  repeated rows and cells of an ODS file included: a row repeated a million times costs a counter, not a million rows,
  until it has a cell.
- **Entries outside the root.** An archive with an entry whose name is absolute, uses a backslash or a drive, holds
  a NUL byte or a `..` segment is refused when it is opened; a relationship whose target goes above the root is
  refused when it is read. Nothing is ever extracted to the disk: the parts are read from the archive.
- **XML.** A part that declares a document type is refused before any entity is expanded: no external entity is
  loaded (XXE) and no entity multiplies (billion laughs). The parser never reaches the network. A part that ends before
  its root element closes is refused as malformed. The warnings of libxml are not raised to the caller: a part that
  cannot be read is refused with a `ReadRefused`.
- **Encrypted parts** are refused.
- **Texts.** A CSV record that is not UTF-8 is refused.

Every refusal is a `ReadRefused`, under `SpreadsheetException`; its message names the limit or the part, never the
content of a cell.

The three readers have fuzz targets: see [Testing](testing.md).

## Writing

- A text that is not UTF-8 is refused with a `WriteFailed` rather than written as a broken file.
- The XLSX and ODS writers write to temporary files they remove, even when writing fails; the stream receives the
  file only once it is complete.
