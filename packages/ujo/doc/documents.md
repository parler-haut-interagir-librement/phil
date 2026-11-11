# Documents

## The JSON form

A document in UJO (Universal JSON Output) is a JSON object of a save time, an optional version and ordered blocks:

```json
{
  "time": 1762387200000,
  "blocks": [
    {"id": "oUq2g_tl8y", "type": "paragraph", "data": {"text": "Hello <b>world</b>"}, "tunes": {"alignment": {"alignment": "left"}}}
  ],
  "version": "2.31.7"
}
```

| Key | Required | Form |
|---|---|---|
| `time` | no | the time it was saved, in milliseconds, a positive integer |
| `blocks` | yes | a list of blocks |
| `version` | no | the version of the editor, a text |
| block `id` | yes | 1 to 64 letters, digits, `_` or `-`, unique in the document, nested blocks included |
| block `type` | yes | a name starting with a letter, up to 64 characters: the type, or one of its aliases |
| block `data` | yes | an object, whose shape the type decides |
| block `tunes` | no | an object of named tunes |

No other key is accepted, at either level.

## Values

`Document` and `Block` are `final readonly` classes. Their constructors take values as they are; `Document::fromArray()`
and `Block::fromArray()` check the structure above and throw `InvalidDocument` with the path of what is wrong.

```php
$block = new Block('a1', 'paragraph', ['text' => 'Hello']);
$block = $block->withData(['text' => 'Bye'])->withTunes(['anchor' => ['anchor' => 'bye']]);

$document = new Document([$block], time: 1762387200000, version: '2.31.7');
$document->toArray();     // the JSON form, `time` and `version` left out when unknown
```

The structure is not the data: a `Block` may hold any data. `Normalizer` checks the data of each block against its
type ([normalization.md](normalization.md)), and `Ujo::decode()` does both.

## Nested blocks

A type may hold blocks in its data: the columns of `columns`, the content of `toggle`, `note`, `descriptive`, `wide`,
`columnFlow` and `container`, the sections of `statblock`. Once normalized, they are `Block` objects inside `data`.

```php
$block->children();                  // the blocks nested directly in this one, in order
$document->walk();                   // every block, depth first, a block before those it holds
$document->find('oUq2g_tl8y');       // a block by identifier, wherever it is, or null
```

`toArray()` writes nested blocks in their own JSON form.

## Reading and writing JSON

`JsonDecoder` reads strictly:

- a text longer than its limit — 4 MiB by default — is refused before parsing;
- invalid JSON, or JSON nested deeper than its limit — 64 by default —, is refused with the parser's reason;
- a top level that is not an object is refused;
- the object must then have the structure above.

```php
$document = new JsonDecoder(maxBytes: 1_048_576, maxDepth: 32)->decode($json);
```

A number too large for an integer is read as a text, so a `time` beyond PHP's integers is refused rather than
rounded. An empty object and an empty list cannot be told apart once decoded: both are accepted where either is.

`JsonEncoder` writes the canonical JSON form: UTF-8 and slashes unescaped, `1.0` kept as a float, and the empty values
of `data`, `tunes`, `meta`, `file` and `settings` written as `{}`. A text that is not valid UTF-8 cannot be written:
`InvalidDocument` says so. `encode($document, pretty: true)` indents the output.

`Ujo::encode()` normalizes before writing, so what is stored is always canonical.
