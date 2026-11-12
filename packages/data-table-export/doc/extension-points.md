# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## `ExportWriter`

`Phil\DataTableExport\Contract\ExportWriter` writes a sheet in one format. Implement it to change how a format is
written, and give it to `ExportWriters` in place of the package's writer.

| Method | Contract |
|---|---|
| `format(): Format` | The format it writes; `ExportWriters` refuses two writers of one format |
| `write(Sheet $sheet, resource $stream): void` | Writes the sheet to the open stream, reading its rows once; throws `ExportRefused` for a sheet past the format's limits, a cell of no kind it writes, or a stream that refuses |

A writer keeps nothing between two sheets, so one instance serves every request of a worker.

## What is not an extension point

`Cells` and `SheetXml` are internal: they may change in any version.
