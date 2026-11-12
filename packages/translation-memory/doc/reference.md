# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\TranslationMemory\LanguageTag` | `final readonly class` | A BCP 47 tag in its canonical case |
| `Phil\TranslationMemory\Text` | `final class` | The words of inline HTML, white space normalized |
| `Phil\TranslationMemory\Fingerprint` | `final readonly class` | The SHA-256 of a normalized source |
| `Phil\TranslationMemory\Variants` | `final readonly class` | The regional variants that inherit from their parent, and the segments they inherit |
| `Phil\TranslationMemory\Xml` | `final class`, internal | The XML of an exchange file read safely |
| `Phil\TranslationMemory\Segment` | `final readonly class` | One piece of a document in one target language, and its transitions |
| `Phil\TranslationMemory\SegmentState` | enum | Untranslated, suggested, translated, reviewed or stale |
| `Phil\TranslationMemory\SourceSegment` | `final readonly class` | A piece of a source as a segmenter cuts it: key and text |
| `Phil\TranslationMemory\SourceChange` | `final readonly class` | What changed in the source of a stale segment, as HTML |
| `Phil\TranslationMemory\SegmentSync` | `final class` | Stored segments brought up to date with a new cut |
| `Phil\TranslationMemory\SyncResult` | `final readonly class` | The segments after a synchronization, and what happened |
| `Phil\TranslationMemory\Segmentation\UjoSegmenter` | `final readonly class` | A UJO document cut into segments and put back together |
| `Phil\TranslationMemory\Segmentation\Granularity` | enum | By block field or by sentence |
| `Phil\TranslationMemory\Segmentation\TranslatableFields` | `final readonly class` | The field names translated and the block types left alone |
| `Phil\TranslationMemory\Segmentation\SentenceSplitter` | `final readonly class` | Inline HTML cut into sentences |
| `Phil\TranslationMemory\Memory\TranslationMemory` | `final readonly class` | Exact and fuzzy matches of a source, and learning |
| `Phil\TranslationMemory\Memory\MemoryStore` | interface | Where translation units are kept |
| `Phil\TranslationMemory\Memory\InMemoryStore` | `final class` | A store in an array |
| `Phil\TranslationMemory\Memory\MemoryUnit` | `final readonly class` | A source, its target, their languages and origin |
| `Phil\TranslationMemory\Memory\MemoryMatch` | `final readonly class` | A unit proposed for a source, its score and kind |
| `Phil\TranslationMemory\Memory\MatchKind` | enum | Exact or fuzzy |
| `Phil\TranslationMemory\Memory\Similarity` | `final class` | Levenshtein distance, score, trigrams, Dice coefficient |
| `Phil\TranslationMemory\Glossary\Glossary` | `final readonly class` | The terms of a pair of languages, found and checked |
| `Phil\TranslationMemory\Glossary\Term` | `final readonly class` | A term, its allowed and refused translations |
| `Phil\TranslationMemory\Glossary\TermHit` | `final readonly class` | A term found in a text |
| `Phil\TranslationMemory\Glossary\TermIssue` | `final readonly class` | An issue of a target against a term |
| `Phil\TranslationMemory\Glossary\TermIssueKind` | enum | Missing or forbidden |
| `Phil\TranslationMemory\Glossary\Contract\Inflector` | interface | The inflected forms of a phrase |
| `Phil\TranslationMemory\Glossary\RegularPlurals` | `final readonly class` | The regular plurals of English and French |
| `Phil\TranslationMemory\Check\Contract\Check` | interface | One quality check of a translated segment |
| `Phil\TranslationMemory\Check\Contract\TextWidth` | interface | How wide a line is drawn, in pixels |
| `Phil\TranslationMemory\Check\Checker` | `final readonly class` | The checks run in turn, those the flags dismiss left out |
| `Phil\TranslationMemory\Check\Checked` | `final readonly class` | A target, its source, their languages and the segment's flags |
| `Phil\TranslationMemory\Check\CheckIssue` | `final readonly class` | What a check found, and its name |
| `Phil\TranslationMemory\Check\Flags` | `final readonly class` | The flags of a segment |
| `Phil\TranslationMemory\Check\Punctuation` | `final readonly class` | The final punctuation |
| `Phil\TranslationMemory\Check\Spaces` | `final readonly class` | White space at both ends, and double spaces |
| `Phil\TranslationMemory\Check\Tags` | `final readonly class` | The tags of the source |
| `Phil\TranslationMemory\Check\Plurals` | `final readonly class` | The forms of ICU plurals |
| `Phil\TranslationMemory\Check\IcuPlurals` | `final class`, internal | The plural arguments of an ICU message |
| `Phil\TranslationMemory\Check\SameAsSource` | `final readonly class` | A target identical to its source |
| `Phil\TranslationMemory\Check\MaxSize` | `final readonly class` | The widest a target may be drawn |
| `Phil\TranslationMemory\Check\AdvanceWidths` | `final readonly class` | A width from the advances of a font |
| `Phil\TranslationMemory\Check\Glyphs` | `final readonly class` | The characters a font of `phil/fonts` draws |
| `Phil\TranslationMemory\Xliff\XliffFile` | `final readonly class` | The languages, original and units of an XLIFF file |
| `Phil\TranslationMemory\Xliff\XliffUnit` | `final readonly class` | One unit: key, source, target, state, note |
| `Phil\TranslationMemory\Xliff\XliffWriter` | `final readonly class` | XLIFF 2.1 written |
| `Phil\TranslationMemory\Xliff\XliffReader` | `final readonly class` | XLIFF 2.x and 1.2 read |
| `Phil\TranslationMemory\Xliff\XliffMerge` | `final class` | An imported file merged into segments |
| `Phil\TranslationMemory\Xliff\MergeResult` | `final readonly class` | The segments after a merge, applied keys, skipped units |
| `Phil\TranslationMemory\Xliff\Skipped` | `final readonly class` | A unit that changed nothing, and why |
| `Phil\TranslationMemory\Xliff\SkipReason` | enum | Unknown key, source changed, no target, unchanged |
| `Phil\TranslationMemory\Xliff\InlineCodes` | `final class`, internal | The tags of inline HTML as runs, kept to those of a source |
| `Phil\TranslationMemory\Tmx\TmxWriter` | `final readonly class` | Translation units written as TMX 1.4b |
| `Phil\TranslationMemory\Tmx\TmxReader` | `final readonly class` | TMX read into translation units |
| `Phil\TranslationMemory\Tbx\TbxWriter` | `final readonly class` | A glossary written as TBX-Basic |
| `Phil\TranslationMemory\Tbx\TbxReader` | `final readonly class` | The terms of a pair of languages read from TBX |
| `Phil\TranslationMemory\Exception\TranslationMemoryException` | interface | Every exception of the library |
| `Phil\TranslationMemory\Exception\InvalidLanguage` | `final class` | A tag that is not one, or a language that does not match |
| `Phil\TranslationMemory\Exception\InvalidSegment` | `final class` | A segment, fingerprint or unit that cannot be built or changed |
| `Phil\TranslationMemory\Exception\InvalidTerm` | `final class` | A glossary term without words |
| `Phil\TranslationMemory\Exception\InvalidXliff` | `final class` | An XLIFF file that cannot be read |
| `Phil\TranslationMemory\Exception\InvalidTmx` | `final class` | A TMX file that cannot be read |
| `Phil\TranslationMemory\Exception\InvalidTbx` | `final class` | A TBX file that cannot be read |
| `Phil\TranslationMemory\Exception\InvalidConfiguration` | `final class` | A setting of the memory or of a check out of range, or a flag that is not one |
