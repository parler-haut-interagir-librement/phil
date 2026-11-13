# Anchoring

A thread is about a passage: a few words a reader selected. Its `Anchor` says where they are and what they say, so
that the thread can find them again once the text has changed.

## An anchor

| Part | Meaning |
|---|---|
| `block` | The identifier of the UJO block the passage is in; `null` in a plain text |
| `field` | The path of the field in the block's data; `null` in a plain text, or a field name of the application's own |
| `start`, `end` | Character offsets in the field's text; `end` follows from the passage's length |
| `quote` | The passage itself, 1 to 2000 characters |
| `prefix`, `suffix` | Up to 32 characters before and after it, kept to tell two equal passages apart |

Offsets count characters — Unicode code points, as PHP's `mb_strlen()` counts them —, never bytes, and never the
UTF-16 units of JavaScript strings: the controller converts. They count the field's **text**: tags left out,
character references decoded, as a browser reads `textContent`. `one <b>two</b> &amp; three` has the text
`one two & three`, and `two` starts at 4.

```php
use Phil\CommentsBundle\Domain\ValueObject\Anchor;

$anchor = Anchor::of('a1B2c3', 'text', 4, 'two', 'one ', ' & three');
```

## Fields of a UJO block

A field's path joins the keys from the block's `data`. A string is a text when its key — or, inside a list, the key
of the list — is one of `text`, `caption`, `title`, `message`, `content`, `description`, `name`, `term`, `subtitle`,
`code` or `latex`; URLs, styles, levels and other settings never are.

| Block | Paths |
|---|---|
| `paragraph`, `header`, `alert`, `toggle` | `text` |
| `quote` | `text`, `caption` |
| `warning` | `title`, `message` |
| `list` | `items.0.content`, `items.0.items.1.content` for a nested item |
| `table` | `content.2.1`, row then column |
| `image`, `embed` | `caption` |
| `definitionList` | `items.0.term`, `items.0.description` |
| `code`, `mermaid` | `code`, plain text |
| `math` | `latex`, plain text |

Fields are inline HTML, read as their text, except the source of `code`, `mermaid` and `math` blocks, which is plain
text. Blocks nested in a toggle or a column are blocks of their own, with their own identifiers. A path has at most
16 keys. `UjoPassages::of($document)` lists every text of a document, in reading order, for a document decoded by
`phil/ujo` (`Ujo::decode()`), whose nested blocks are `Block` objects.

## Finding a passage again

`Reanchoring::locate($anchor, $passages)` searches the texts given, in reading order, for every place where the
passage's exact text occurs, overlapping places included, and keeps the best:

1. its own field first, then another field of its block, then the rest of the document — a passage an edit moved
   usually stays where it was;
2. among places of the same rank, the one whose surroundings share the most characters with the kept prefix and
   suffix;
3. then, in its own field, the place nearest its old offset; elsewhere, the first in reading order.

The anchor returned has the new place and the context found there. The choice is deterministic: the same anchor in the
same texts always lands on the same place, and on a full tie the first in reading order wins. When the passage occurs
nowhere, `null`: the passage has disappeared. An edit inside the passage — a word changed in the middle of it — makes it
disappear: the passage is found again by its exact text, never approximately. The other way round, a passage that
disappears while the same words remain elsewhere moves to them: a short passage, a single common word, is the most
exposed, and a longer selection is the safer anchor.

The texts are searched by bytes, their characters counted once, from one place to the next, and each place's
context compared byte by byte with the context kept, so that a long field with many places costs one pass: a passage
found again among the hundred blocks of a document takes about a tenth of a millisecond, and a field of a hundred
thousand characters where a four-letter passage occurs at every place a few hundred milliseconds (see
[testing](testing.md)).

## Following the text

The bundle does not know the documents: the application tells it when one changes, after saving it.

```php
$comments->reanchorDocument(ResourceReference::of('article', '42'), $document);   // a UJO document
$comments->reanchorText(ResourceReference::of('note', '7'), $text);               // a plain text
$comments->reanchor($subject, $passages);                                          // texts of its own
```

Each thread of the resource is moved to where its passage is now, its context refreshed — to another occurrence of
the same words when its own has gone —, or detached, orphaned, when the words occur nowhere; an orphaned thread whose
passage comes back — an undo, a paste — is attached again. Threads on the whole page have no passage and are left as
they are. Only threads that changed are saved. The result counts them: `['updated' => 2, 'detached' => 1]`.

An orphaned thread keeps its last place and its quote. The endpoints answer it with `"detached": true`, and the panel
lists it after the others, its quote struck through, under the words *Orphaned: the quoted passage is no longer in
the text*; it can still be answered, resolved or reopened, and its suggestion rejected, never accepted.

In the browser, the controller finds each passage in the editor as it is being written, with the same rules in
JavaScript, to highlight it; the server's anchors catch up when the application saves and re-anchors.
