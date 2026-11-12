# Testing

## The library's tests

The suite `tests/Unit` tests language tags and fingerprints; every transition of a segment and every refusal; the
sentence splitter on ends, closers, abbreviations, initials, CJK ends and tags, each text given back whole; the cutting
of documents by field and by sentence, nested blocks, keys that cannot be named and values too deep, and the putting
back of segments in accepted states and on the same source only; synchronization — kept, moved, stale, added, removed;
the Levenshtein distance against every bound, trigrams, the window of lengths, the shortlist, the order and the limit
of matches, and the units of a parent language read for a variant; regional variants and their inherited targets; the
glossary's finding and checking, inflected forms and regular plurals; each quality check on what it accepts and what it
reports, flags, the ICU plural reader on balanced and broken messages, widths and glyphs; XLIFF written exactly, read
in 2.x and 1.2 with every inline code and state, refused, and merged; TMX and TBX written exactly, read back, read
from other tools, and refused.

From the monorepo root:

```sh
vendor/bin/phpunit packages/translation-memory/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

Three fuzz targets in `tests/Fuzz` run the parsers on inputs nobody wrote, from the seeds of `tests/Fuzz/corpus`:
`tmx-reader` and `tbx-reader` read units and glossaries or refuse the file with their exception, and `plurals` runs the
plural check on any source and target; none may throw anything else or warn.

```sh
composer fuzz -- tmx-reader tbx-reader plurals
```

## Benchmarks

`TranslationMemoryBench` looks a segment up in memories of 100, 1 000 and 10 000 units, and scores segments of 100 and 1
000 characters near and far from each other. From the monorepo root:

```sh
composer bench -- packages/translation-memory/tests/Benchmark
```

## Testing code that translates

Every service is deterministic and holds no state but its store. Build segments directly, use an `InMemoryStore`, and
compare results exactly:

```php
$memory = new TranslationMemory(new InMemoryStore([
    new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'Hello', 'Bonjour'),
]));

self::assertSame('Bonjour', $memory->lookup('Hello', LanguageTag::of('en'), LanguageTag::of('fr'))[0]->unit->target);
```

For XLIFF, read back what was written rather than comparing files: `new XliffReader()->read($xml)->units`.
