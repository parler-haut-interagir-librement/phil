# Comparing documents

`TextDiff::documents()` compares two documents in UJO (Universal JSON Output), the block document format of `phil/ujo`,
block by block, and returns a `DocumentDiff`.

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();
$diff = $differ->documents($ujo->decode($oldJson), $ujo->decode($newJson));

$diff->isIdentical();   // nothing changed
$diff->changes();       // the blocks that did not stay as they were
$diff->topLevel();      // the blocks at the top of either document
$diff->blocks;          // every block of either document, nested ones included
```

Compare documents in their canonical form — decoded by `Ujo::decode()` or passed through `Ujo::normalize()` — so that
two spellings of the same data, an alias of a block type or a list in its old shape, do not read as changes. The HTML
of a difference does not rely on it: each block is normalized again before it is rendered (see below).

## Pairing blocks

In UJO each block has an identifier that stays with it through edits. At each level — the top of the document,
the blocks of a column, of a toggle, of a note — the identifiers of the old and new blocks are compared in order, by
the same algorithm as texts:

| `BlockChangeKind` | When |
|---|---|
| `Unchanged` | the identifier is kept in place, and the block's type, data, tunes and nested blocks are the same |
| `Changed` | the identifier is kept in place, and something in the block or in a block nested in it changed |
| `Moved` | the identifier left its place and came back elsewhere at the same level; its fields say whether it was also edited |
| `Added` | the identifier is only in the new document |
| `Removed` | the identifier is only in the old document |

A moved block is listed once, where it now stands. A block that moves to another level — out of a column, into a
toggle — reads as removed from one and added to the other. Past the bound of edits ([texts.md](texts.md)), every block
of the middle is either moved or added and removed, never kept: still correct, less precise.

Two blocks with the same identifier in one document are not valid UJO; the comparison does not fail on them, but pairs
them as it can.

## A `BlockChange`

| Property | Value |
|---|---|
| `kind` | the `BlockChangeKind` |
| `id`, `type` | the identifier and type of the new block, or of the old one when removed |
| `parent` | the identifier of the block it is nested in, `null` at the top |
| `oldIndex`, `newIndex` | its position among its siblings in each document, `null` where it is not |
| `old`, `new` | the `Block` in each document, `null` where it is not |
| `fields` | the `FieldChange`s of the block itself, nested blocks apart |

`before()` and `after()` give the old and new block as lists of zero or one block, ready for a `Document`.

`DocumentDiff::$blocks` lists every block in the reading order of the new document: a block before the blocks nested
in it, a removed block where it stood.

## Field changes

Two paired blocks are compared value by value: their `type`, their `data` and their `tunes`. Each value that differs
is a `FieldChange`:

| Property | Value |
|---|---|
| `path` | where the value is in the block: `type`, `data.text`, `data.items.0.content`, `tunes.alignment.alignment` |
| `old`, `new` | the value in its JSON form, `null` where it is missing |
| `diff` | when both values are texts, their `Diff` by `Tokenizer::Html`: UJO text fields hold inline HTML, kept whole |

Arrays are compared key by key, so a value added to a block is a change from `null`, and one taken out a change to
`null`. **Lists are compared by position**: an item inserted at the top of a list changes every item after it. Lists
of blocks are not values: they are a level of their own, compared by identifier as above, and their blocks are listed
as `BlockChange`s whose `parent` is the block that holds them. A container whose only change is in a nested block is
`Changed` with no field of its own.

## HTML

`TextDiff::documentToHtml()` writes a document difference as HTML, one element per top-level block:

```html
<div class="text-diff-block text-diff-unchanged"><h2 id="b" class="ujo-header">Title</h2></div>
<div class="text-diff-block text-diff-removed"><del><p class="ujo-paragraph">Gone</p></del></div>
<div class="text-diff-block text-diff-moved"><p class="ujo-paragraph">Hello <ins>there </ins>world</p></div>
<div class="text-diff-block text-diff-added"><ins><p class="ujo-paragraph">New</p></ins></div>
```

Each block is first normalized by `phil/ujo`'s `Normalizer` — the one given to the constructor,
`new TextDiff(normalizer: $normalizer)`, or a default one —, then rendered by its `HtmlRenderer` — the one given as
second argument, with the application's renderers, highlighter and filters, or `HtmlRenderer::default()`. The
normalization is a safety requirement, not a convenience: the renderer escapes texts and sanitizes HTML fields, but
trusts the addresses, classes and styles the normalizer checks, so a block read by `Document::fromArray()` or
`JsonDecoder::decode()` alone could carry a `javascript:` link into the page. Give the normalizer the application reads
its documents with, so that it knows the same types; a block that breaks its type throws
`Phil\Ujo\Exception\InvalidDocument`. An added block is wrapped in `<ins>`, a removed one in
`<del>` (both may hold blocks in HTML5); any other is the difference of its old and new HTML by `Tokenizer::Html`, as
[html.md](html.md) describes, which shows the changes of nested blocks inside their container. A block whose type
the normalizer or the renderer does not know throws `Phil\Ujo\Exception\UnknownBlockType`.

The class ends with the kind's value. A style sheet can, for example, mark the side of changed blocks:

```css
.text-diff-added, .text-diff-removed, .text-diff-changed, .text-diff-moved { border-inline-start: 3px solid; padding-inline-start: .5rem; }
.text-diff-added { border-color: #2da44e; }
.text-diff-removed { border-color: #cf222e; }
.text-diff-changed { border-color: #bf8700; }
.text-diff-moved { border-color: #0969da; }
```
