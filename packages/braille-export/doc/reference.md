# Reference

Every public type of the package, with its role. The classes of the `Internal` namespaces are not part of it.

| Type | Role |
|---|---|
| `BrailleExporter` | Lays a document out in braille pages: one batch to the translator, then lines, pages, sections and volumes. |
| `Contraction` | `Uncontracted`, grade 1, or `Contracted`, grade 2. |
| `Contract\Translator` | The port: texts to Unicode braille, by language and contraction. |
| `Contract\Block` | A block of a document. |
| `Document\Document` | A document: its blocks, language and title. |
| `Document\Heading` | A heading of level 1 to 6. |
| `Document\Paragraph` | A paragraph. |
| `Document\ListBlock`, `Document\ListItem` | A list and its items, nested. |
| `Document\Table` | A table, linearized by the layout. |
| `Document\PageBreak`, `Document\SectionBreak` | A new page, a new section. |
| `Document\PrintPage` | Where a page of the print edition starts. |
| `Translation\UncontractedTranslator` | Uncontracted French and English, in PHP. |
| `Translation\ChainTranslator` | The first of several translators that supports the language. |
| `Translation\Braille` | The helpers of Unicode braille: a cell of dots, a check, a length. |
| `Layout\PageFormat` | The size of a page, its page numbers, volumes, duplex and the page limit. |
| `Book\Book`, `Book\Volume`, `Book\Section`, `Book\Page` | A laid-out book, its volumes, sections, pages and rows. |
| `Output\BrfWriter` | A book as a BRF file. |
| `Output\PefWriter`, `Output\PefMetadata` | A book as a PEF 2008-1 file, and its metadata. |
| `Output\UnicodeWriter` | A book as Unicode braille text. |
| `Ujo\UjoReader` | A UJO document read as a document. |
| `Exception\BrailleExportException` | Every exception of the package. |
| `Exception\TranslationFailed` | A translation that could not be done. |
| `Exception\UnsupportedTranslation`, `Exception\UntranslatableCharacter`, `Exception\InvalidBraille`, `Exception\DocumentTooLong`, `Exception\UnrepresentableCell` | See [Exceptions](exceptions.md). |
