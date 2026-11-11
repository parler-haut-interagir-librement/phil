# Plain text

`Text\NotesPlainText` gives a document with its notes as text, for a search index or an accessibility check.

```php
use Phil\FootnotesSidenotes\Text\NotesPlainText;

echo new NotesPlainText()->convert($document, $notes);
```

```text
Text with a note[1] and another[2].

[1] The first note.
[2] The second.
```

Each reference is written `[label]`; a reference without a note is left out. The notes come once, after the last block,
one per line, on one line each. The `PlainText` of `phil/ujo` draws the blocks: without this class, a number stored in a
reference joins the word before it and each note stands where its block does.

A `PlainText` given to the constructor keeps the caller's conversions.
