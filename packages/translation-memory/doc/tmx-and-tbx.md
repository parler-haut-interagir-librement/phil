# TMX and TBX

A translation memory leaves and arrives as TMX, a glossary as TBX: the exchange formats translation tools read and
write. Both readers refuse a file that declares a document type before parsing it, fetch nothing from the network and
expand no entity.

## TMX 1.4b

`TmxWriter` writes the units of one source language — units of another source language are left out:

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tmx\TmxReader;
use Phil\TranslationMemory\Tmx\TmxWriter;

$xml = new TmxWriter()->write(LanguageTag::of('en'), $units);   // iterable<MemoryUnit>
$units = new TmxReader()->read($xml);                          // list<MemoryUnit>
```

```xml
<tmx version="1.4">
  <header creationtool="phil/translation-memory" creationtoolversion="0.1" segtype="sentence" o-tmf="phil" adminlang="en" srclang="en" datatype="html"/>
  <body>
    <tu>
      <prop type="x-origin">page:1</prop>
      <tuv xml:lang="en"><seg>A <bpt i="1">&lt;b&gt;</bpt>bold<ept i="1">&lt;/b&gt;</ept> move</seg></tuv>
      <tuv xml:lang="fr"><seg>Un coup <bpt i="1">&lt;b&gt;</bpt>audacieux<ept i="1">&lt;/b&gt;</ept></seg></tuv>
    </tu>
  </body>
</tmx>
```

Each unit is a `<tu>` with its source and its target as `<tuv>`; its origin, when known, a `x-origin` property. Tags
stay out of the words a tool may edit: an opening tag and the closing tag that matches it become `<bpt>` and `<ept>`
sharing a number, any other tag a `<ph>`, each holding the HTML it stands for.

`TmxReader` reads TMX 1.4b, and the earlier versions as far as they agree. The source of a `<tu>` is the `<tuv>` of its
own `srclang`, or of the header's; with `*all*`, its first `<tuv>`. Every other `<tuv>` gives a unit from that source
into its language, `xml:lang` or the older `lang`. Inline codes come back as the HTML they hold; `<hi>` and `<sub>` keep
their words. A `<tuv>` without words, or a `<tu>` without its source, is left out. A file holds 100 000 units at most
(`TmxReader::MAX_UNITS`).

To load a file into a memory:

```php
foreach (new TmxReader()->read($xml) as $unit) {
    $store->add($unit);
}
```

`InvalidTmx` is thrown for a text that is not well-formed XML with a `<tmx>` root, a document type, a missing
`<header>` or `<body>`, a language that is not a tag, or too many units.

## TBX

`TbxWriter` writes a glossary as TBX of ISO 30042:2019, in the TBX-Basic dialect and its DCA style; `TbxReader` reads
the terms of one pair of languages from that TBX or from the TBX of 2008, whose root is `<martif>`.

```php
use Phil\TranslationMemory\Tbx\TbxReader;
use Phil\TranslationMemory\Tbx\TbxWriter;

$xml = new TbxWriter()->write($glossary);
$glossary = new TbxReader()->read($xml, LanguageTag::of('en'), LanguageTag::of('fr-CA'));
```

| Glossary | TBX |
|---|---|
| A term | A `<conceptEntry>` (`<termEntry>` in 2008) |
| Its note | A `<note>` of the entry; read back from a `<note>` or a definition |
| The term | The preferred term of the source language's `<langSec>` (`<langSet>`) |
| Its inflected forms | Admitted terms of the source language |
| Its first translation, or the term when it is kept | The preferred term of the target language |
| The other translations and their forms | Admitted terms of the target language |
| Refused translations | Deprecated terms of the target language |

Each term is a `<termSec>` (`<tig>` or `<ntig>` in 2008) with its `administrativeStatus`. When reading, the source term
is the preferred one, or else the first that is not deprecated; the other source terms that are not deprecated are its
forms. The target terms that are not deprecated are its translations; the deprecated, superseded and not recommended
ones are refused — a term whose every target term is refused reads as kept. An entry without both languages is left
out. A language matches its parent: an entry with a `fr` section and no `fr-CA` one is read for `fr-CA`.

TBX has no place for case: a case-sensitive term is read back as one that ignores case. A file holds 100 000 entries at
most (`TbxReader::MAX_ENTRIES`). `InvalidTbx` is thrown for a text that is not well-formed XML with a `<tbx>` or
`<martif>` root, a document type, a language that is not a tag, or too many entries.
