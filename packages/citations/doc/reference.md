# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Model\Reference` | A cited document: id, type, names, dates and variables, as CSL-JSON keeps them. |
| `Model\ItemType` | The 45 item types of CSL 1.0.2; `isStandalone()` for a document published on its own. |
| `Model\NameVariable` | The name variables of CSL: `author`, `editor`, `translator`… |
| `Model\DateVariable` | The date variables of CSL: `issued`, `accessed`… |
| `Model\Name` | A name in its parts, or a literal. |
| `Model\PartialDate` | A year, with a month and a day, or a season, when known. |
| `Model\CslDate` | A date variable: a partial date or a range, approximate or not, or a literal. |
| `CslJson` | Reads and writes CSL-JSON. |
| `Import\BibTeXReader` | Reads BibTeX into references. |
| `Import\RisReader` | Reads RIS into references. |
| `Import\ImportLimits` | The bounds of an import. |
| `ReferenceList` | The references a text may cite, without duplicates. |
| `Cite` | One reference cited at one place, with its locator and its words before and after. |
| `Citation` | The cites of one place of a text. |
| `Locator` | What a locator points to: page, chapter, section… |
| `CitationProcessor` | Renders the citations and the bibliography of a text in a style. |
| `RenderedCitations` | The rendered citations and bibliography. |
| `RichText` | Formatted text, given as plain text or escaped HTML. |
| `Terms` | The words and typography of a language. |
| `Contract\CitationStyle` | A citation style. |
| `Style\AbstractStyle` | The helpers the library's styles share. |
| `Style\AuthorDate` | An author-date style in the manner of APA. |
| `Style\Notes` | A notes and bibliography style in the manner of Chicago. |
| `Style\Numeric` | A numeric style in the manner of IEEE. |
| `Style\BibliographyOrder` | Alphabetical, or in the order of first citation. |
| `Style\Entry` | A reference in a bibliography: its number and its year letter. |
| `Style\CitedItem` | One cite of a citation, with its entry and whether it is the first. |
| `Exception\CitationsException` | Implemented by every exception of the library. |
| `Exception\InvalidReference` | A reference or CSL-JSON that breaks the model. |
| `Exception\InvalidImport` | BibTeX or RIS that cannot be read, with its line. |
| `Exception\UnknownReference` | A citation of an id the list does not hold. |
| `Exception\MissingTerms` | No terms for a language. |
