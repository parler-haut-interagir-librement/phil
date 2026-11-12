# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\DataTableExport\Sheet` | A title, the headings and the rows, read once. |
| `Phil\DataTableExport\Format` | The formats, `Csv` and `Xlsx`, with `named()`, `mediaType()` and `fileName()`. |
| `Phil\DataTableExport\ExportWriters` | One writer per format; `defaults()`, `formats()`, `has()`, `get()` and `write()`. |
| `Phil\DataTableExport\Contract\ExportWriter` | Writes a sheet in one format to a stream. |
| `Phil\DataTableExport\Csv\CsvWriter` | CSV after RFC 4180, with its separator, byte order mark and formula guard. |
| `Phil\DataTableExport\Csv\FormulaGuard` | Keeps a text that a spreadsheet would run as a formula as text. |
| `Phil\DataTableExport\Xlsx\XlsxWriter` | An XLSX workbook of one worksheet, with its temporary directory and row limit; `worksheetName()`. |
| `Phil\DataTableExport\Exception\ExportException` | Every exception of the package. |
| `Phil\DataTableExport\Exception\ExportRefused` | A sheet, a format or a setting the package cannot write. |
| `Phil\DataTableExport\Cells` | Internal: a cell brought to the kinds the writers know. |
| `Phil\DataTableExport\Xlsx\SheetXml` | Internal: the XML of the cells of a worksheet. |
