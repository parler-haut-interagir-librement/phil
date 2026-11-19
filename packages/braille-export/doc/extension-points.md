# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Translate with other tables, or another language | Implement `Contract\Translator`, or use `phil/braille-export-liblouis` |
| Fall back to uncontracted braille | `ChainTranslator`, the PHP translator last |
| Find the characters a text holds without braille | `new UncontractedTranslator(strict: true)` |
| Change the size of the pages, the page numbers, the volumes | `PageFormat`, given to `BrailleExporter` |
| Give a PEF file its identifier, author, publisher and date | `PefMetadata`, given to `PefWriter::write()` |
| Change the text of a UJO block, or read a type of its own | A `PlainText` of `phil/ujo` with `with()`, given to `UjoReader` |
| Read a document of another format | Build a `Document` of its blocks |
| Write another format | Read the `Book`: its volumes, sections, pages and rows |

`BrailleExporter`, the translators and the writers are final: an application decorates them, or writes its own on the
port.
