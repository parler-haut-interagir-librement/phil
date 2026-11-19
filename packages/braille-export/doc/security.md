# Security

A document may come from anybody: a member's text, an imported file. What it can do is bounded.

- **Its text is data.** It is translated cell by cell; nothing of it is run, read as markup or used as a path. A text
  that is not valid UTF-8 has its invalid bytes replaced before anything reads it.
- **Its size is bounded.** `PageFormat::$maxPages`, 10,000 by default, stops a layout with `DocumentTooLong`; the
  indentation of nested lists is bounded too. The translation walks each text once, and the layout each word once.
- **What it writes is braille.** Every text a translator gives back is checked to be braille cells, one per text sent,
  before the layout reads it, so that a translator cannot slip text into a file. A PEF file escapes its metadata and
  leaves out what XML 1.0 cannot hold; its rows hold braille cells only.
- **Nothing is kept.** The exporter, the translators and the writers hold no state between two documents.

The layout is fuzzed with any text, in French and in English, as each kind of block: every row is braille, no row is
wider than its line, no page longer than its page, and the BRF file can always be written. See [Testing](testing.md).

A translator that starts a program bounds what it gives the program; see the security notes of that package.
