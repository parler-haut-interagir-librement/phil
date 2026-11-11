# Normalization

`Normalizer::normalize()` checks every block of a document against its type and returns the document in canonical
form. `Ujo::decode()`, `encode()`, `toHtml()` and `toText()` all call it. A document the normalizer returned is canonical
already: given to the same normalizer again — rendered or encoded after it was decoded —, it is returned as it is,
without a second check, unless it is asked at a level of validation it was not checked at.

## Validation levels

A document is checked at one of two `Validation` levels, given to `Normalizer::normalize()`, `Ujo::normalize()` and
`Ujo::decode()`:

| Level | Checks | When |
|---|---|---|
| `Validation::Shape`, the default | the structure, each value of its type and form, the values a type requires present, the bounds a value keeps to be rendered — a heading level of 1 to 6 —, the limits below, sanitizing | every save, a draft included |
| `Validation::Publication` | the shape, and what a document needs to be read: the values a type requires filled in, the bounds it sets on texts and lists, its `publication` hook | publishing, scheduling, sending for review |

```php
$draft = $ujo->decode($json);                                   // saved as it is being written
$ready = $ujo->decode($json, Validation::Publication);          // refused while a heading is empty
```

`encode()`, `toHtml()` and `toText()` check the shape: a draft can be previewed. A document checked for publication
has passed the shape too, and is not checked again for it; a document checked for its shape is checked again when it
is asked for publication.

What the standard types need to be published:

| Type | To publish |
|---|---|
| `header`, `quote` | a text a reader sees, not only tags or white space |
| `list` | an item at least, each item's content filled in, nested items included |
| `code` | code other than white space |
| `table` | a row at least |
| `personality` | a name |

## What it does, block by block

1. **Reads the structure** of the block (`Block::fromArray()`).
2. **Finds the type** by name or alias in its `BlockTypeRegistry`; an unknown type throws `UnknownBlockType`. The
   block is counted: past `maxBlocks` blocks in the document, nested ones included — 10,000 by default —, or past the
   `maxPerDocument` of its type, it is refused.
3. **Folds flat children**: a type with a `fold` hook — `toggle`, for the toggle and collapsible blocks — says how
   many of the following blocks belong inside; they move to `data.blocks`. A count larger than what follows, or
   negative, throws `InvalidDocument`.
4. **Records the identifier**, refusing one already used anywhere in the document.
5. **Upgrades** older and third-party forms with the type's `upgrade` hook, which sees the name the block was saved
   under.
6. **Checks the data** against the type's `Shape`: required values present, no unknown value (unless the shape is
   open), each value of its field — sanitized, bounded, of its form —, defaults written, values in the shape's order.
7. **Runs the `check` hook** for rules between values: rows of a table of one length, an embed on a host of its service.
8. **For publication**, checks the values declared `filled()` and the bounds of the `Bounded` fields as each shape is
   read, then **runs the `publication` hook** for the rules between values a published document keeps.
9. **Checks the tunes** by name or alias in its `TuneRegistry`, each under its canonical name.

Nested blocks go through the same steps, one level deeper; past `maxDepth` levels — 8 by default — they are refused.
Each block keeps its identifier; the type becomes the canonical name.

## Limits

The limits bound what rendering a document costs, not only its size: every HTML field is sanitized again at each
render, and a table of contents or a list of abbreviations reads the whole document.

| Limit | Default | Refuses |
|---|---|---|
| `maxDepth` | 8 (`DEFAULT_MAX_DEPTH`) | blocks nested deeper |
| `maxBlocks` | 10,000 (`DEFAULT_MAX_BLOCKS`) | a document of more blocks, nested ones included |
| `maxHtmlLength` | 100,000 (`DEFAULT_MAX_HTML_LENGTH`) | an HTML field longer, in characters, before it is sanitized |
| `maxRawLength` | 50,000 (`DEFAULT_MAX_RAW_LENGTH`) | the same for a field of the `raw` profile, the HTML of a `raw` block |
| `BlockType::$maxPerDocument` | 10 for `toc` and `abbreviations`, none otherwise | more blocks of the type in one document |

Errors name their path, `blocks[2].data.items[0].content`. A child folded into a toggle is named under its toggle,
`blocks[0].data.blocks[1]`.

## Configuring

```php
$normalizer = new Normalizer(
    types: BlockTypeRegistry::default()->with(StandardTypes::raw())->without('chart'),
    tunes: TuneRegistry::default(),
    sanitizer: new InlineSanitizer(['rich' => $myProfile]),
    maxDepth: 4,
    maxBlocks: 500,
    maxHtmlLength: 20_000,
    maxRawLength: 10_000,
);
```

The default registry holds every type of [block-types.md](block-types.md) but `raw`: raw HTML is accepted only where
it is added.

## Adding a type

A type is a name, a `Shape` of `Property` values and, when needed, the four hooks and a `maxPerDocument`, the most
blocks of the type one document may hold — give one to a type whose renderer reads the whole document:

```php
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Schema\{Bounded, Choice, Html, ListOf, Plain, Property, Shape};
use Phil\Ujo\Type\BlockType;

$callout = new BlockType('callout', new Shape([
    'tone' => Property::defaults(new Choice(['calm', 'loud']), 'calm'),
    'text' => Property::required(new Html())->filled(),
    'points' => Property::defaults(new Bounded(new ListOf(new Plain(200)), 1, 5), []),
]), aliases: ['Callout'], publication: static function (array $data, string $path): void {
    if ('loud' === $data['tone'] && [] === $data['points']) {
        throw InvalidDocument::at($path.'.points', 'a loud callout lists its points to publish');
    }
});

$registry = BlockTypeRegistry::default()->with($callout);
```

| Field | Value |
|---|---|
| `Html($profile = 'rich')` | a text, sanitized with a profile, at most `maxHtmlLength` characters (`maxRawLength` for `raw`) |
| `Plain($maxLength, $pattern)` | a text kept as it is; `Plain::token()`, `length()`, `percentage()`, `color()` |
| `Integer($min, $max)` | a whole number |
| `Boolean` | `true` or `false` |
| `Choice($choices)` | one text of a list |
| `Link($schemes, $relative)` | an address of the schemes, or relative |
| `ListOf($item, $min, $max)` | a list of values of one field |
| `Shape($properties, $open)` | an object; open, it keeps unknown values after the declared ones |
| `Blocks` | nested blocks |
| `Lazy($factory)` | a field built when used, for shapes that hold themselves |
| `Bounded($field, $min, $max)` | a `Sized` field whose size is within bounds for publication; a draft may be shorter or longer |

`Property::required()`, `optional()` — left out when absent — and `defaults()` — written with its default when
absent —, each `->nullable()` to accept `null` and `->filled()` to be filled in for publication: present, not `null`,
of a size above zero when its field is `Sized`, not empty otherwise. A value absent and written with its default is
checked too.

`Html`, `Plain`, `Link`, `ListOf`, `Blocks`, `Dictionary` and `Bounded` are `Sized`: `size()` counts the characters a
reader sees of a text — tags left out, character references read, white space at both ends left out —, the items of
a list, the blocks or the entries; `unit()` names what it counts, for the messages. A field of your own implements it
to be bounded. Give the new type a renderer too ([html-rendering.md](html-rendering.md)).
