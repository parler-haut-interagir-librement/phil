<!-- phil-dev source-sha256=fac7cd21f380878cb51f27935c1b5636b751a15bde6240bf915fabb5150aa7cd -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\DataTableExport\Sheet` | Un titre, les en-têtes et les lignes, lues une seule fois. |
| `Phil\DataTableExport\Format` | Les formats, `Csv` et `Xlsx`, avec `named()`, `mediaType()` et `fileName()`. |
| `Phil\DataTableExport\ExportWriters` | Un écrivain par format ; `defaults()`, `formats()`, `has()`, `get()` et `write()`. |
| `Phil\DataTableExport\Contract\ExportWriter` | Écrit une feuille dans un format, dans un flux. |
| `Phil\DataTableExport\Csv\CsvWriter` | Du CSV selon la RFC 4180, avec son séparateur, sa marque d'ordre des octets et sa garde contre les formules. |
| `Phil\DataTableExport\Csv\FormulaGuard` | Garde comme du texte un texte qu'un tableur exécuterait comme une formule. |
| `Phil\DataTableExport\Xlsx\XlsxWriter` | Un classeur XLSX d'une feuille de calcul, avec son répertoire temporaire et sa limite de lignes ; `worksheetName()`. |
| `Phil\DataTableExport\Exception\ExportException` | Chaque exception du paquet. |
| `Phil\DataTableExport\Exception\ExportRefused` | Une feuille, un format ou un réglage que le paquet ne peut pas écrire. |
| `Phil\DataTableExport\Cells` | Interne : une cellule ramenée aux sortes que connaissent les écrivains. |
| `Phil\DataTableExport\Xlsx\SheetXml` | Interne : le XML des cellules d'une feuille de calcul. |
