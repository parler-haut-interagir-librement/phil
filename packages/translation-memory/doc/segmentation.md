# Segmentation

`UjoSegmenter` cuts a UJO document — a `Phil\Ujo\Document` — into `SourceSegment`s, and puts translated segments back.

## What is translated

Every block, nested ones included, in reading order: a block comes before the blocks it holds. In each block, every
text field whose name — or, inside a list, the name of the list — is one of `TranslatableFields::NAMES`: `text`,
`caption`, `title`, `message`, `content`, `description`, `name`, `term`, `subtitle`, `alt`, `label`, `summary`. Blocks of
the types in `TranslatableFields::UNTRANSLATED_TYPES` — `code`, `mermaid`, `math`, `raw` — are left alone. Both lists
are the defaults of `TranslatableFields`, which takes others:

```php
new UjoSegmenter(fields: new TranslatableFields([...TranslatableFields::NAMES, 'quote'], ['code', 'raw']));
```

A field without words — empty, white space, tags only — makes no segment. Keys of data that are not 1 to 64 letters,
digits or `_`, and values nested more than 16 levels deep, are not read.

## Keys

A key joins the block identifier and the path of the field in the block's data:

| Field | Key |
|---|---|
| The text of a paragraph | `b3/text` |
| The first item of a list | `b7/items.0.content` |
| A nested item | `b7/items.0.items.1.content` |
| A table cell | `b9/content.1.2` |
| The second sentence of a paragraph, by sentence | `b3/text#2` |

Keys stay the same as long as the blocks keep their identifiers, which UJO keeps from one save to the next.

## By sentence

`Granularity::Sentence` cuts each field with a `SentenceSplitter`. A sentence ends at `.`, `!`, `?` or `…`, with the
closing quotes, brackets and tags that follow — a French `»` after its space too —, when white space comes next and the
next word does not start with a lower-case letter. At `。`, `！` or `？` it ends even without white space. A period after
a known abbreviation (`Mr.`, `Dr.`, `e.g.`, `M.`, `Mme.`, `c.-à-d.` and the others of
`SentenceSplitter::ABBREVIATIONS`) or after a single letter — an initial — ends nothing. Tags are never cut, and the
sentences give the text back exactly when joined. The splitter takes its own abbreviations:

```php
new UjoSegmenter(Granularity::Sentence, splitter: new SentenceSplitter([...SentenceSplitter::ABBREVIATIONS, 'approx']));
```

## Putting segments back

```php
$translated = $segmenter->assemble($document, $segments);
$preview = $segmenter->assemble($document, $segments, [SegmentState::Translated, SegmentState::Reviewed, SegmentState::Suggested]);
```

Each piece is replaced by the target of its segment when the segment is in one of the accepted states — translated and
reviewed by default — and was made from that very text: same fingerprint. Every other piece stays in the source
language, so a document is always whole. The white space around each sentence is kept. The result is a new
`Document`; nothing else in it changes. A target is inline HTML written by a translator or brought by a file: render
the document through `phil/ujo`, whose sanitizer keeps only the inline tags it allows.

## Synchronizing

When the source changes, cut it again and bring the stored segments up to date:

```php
$result = SegmentSync::sync($stored, $segmenter->segment($document));
```

Each source piece is matched with, in this order: the stored segment of the same key and fingerprint, kept; a stored
segment of the same fingerprint under another key that has a target — a sentence pushed down by a new one, a block moved
into a column —, carried to the new key, one of the same field first; the stored segment of the same key, whose source
changed — stale when it has a target, untranslated otherwise; or nothing — a new untranslated segment.

| `SyncResult` | Holds |
|---|---|
| `segments` | Every segment of the source, in its order |
| `added` | The keys of the new segments |
| `stale` | The keys of the segments whose source changed under a target |
| `moved` | New key ⇒ old key, for the segments carried to another key |
| `removed` | The stored segments no longer in the source |
| `hasChanges()` | Whether any of the four lists is not empty |
