# HTML output

`TextDiff::toHtml()` — or `HtmlFormatter::format()` — writes a `Diff` as HTML: kept text as it is, deleted text in
`<del>`, inserted text in `<ins>`. The elements carry no attribute; style them as the page wants.

## Plain text

A difference made by `Words`, `Lines` or `Paragraphs` is plain text: every part is escaped (`&`, `<`, `>`, `"` and
`'`), and line breaks are kept as they are.

```php
echo $differ->toHtml($differ->lines("one\ntwo", "one\n2"));
// one
// <del>two</del><ins>2</ins>
```

Show it in an element styled `white-space: pre-wrap` to see the line breaks and the paragraphs.

## HTML

A difference made by `Html` is markup: give it HTML that is already purified — the output of `phil/ujo`'s renderer, of
`phil/markup`'s purifier, or of any sanitizer —, since its tags are written as they are. The result **keeps the
structure of the new HTML**:

- kept tags are written as they are;
- inserted tags are written as they are, and inserted text between them is wrapped in `<ins>`;
- deleted tags are left out, and deleted text between them is wrapped in `<del>`, where it stood.

The text between tags — kept, deleted or inserted — is escaped (`&`, `<`, `>`, `"` and `'`), known character
references (`&amp;`, `&#233;`) kept as they are: no text of the old HTML becomes markup of the new one. A tag is read by the
grammar of its attributes, so that a `>` in a quoted value, `<abbr title="a>b">`, stays inside the tag, and a `<` that
does not open a whole tag is text. The content of `<script>` and `<style>`, which is not HTML, is not compared as
such: give HTML without them.

So `<ins>` and `<del>` only ever wrap text, never a tag, and the result is exactly as well formed as the new HTML.

```php
echo $differ->toHtml($differ->html('<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world!</p>'));
// <p>Hello <del>big</del><i><ins>small</ins></i> world<ins>!</ins></p>
```

White space alone between two tags of a change is not marked: inserted, it is written as it is; deleted, it is left
out. This keeps `<ins>` and `<del>` out of places where only elements may stand, between two list items or two table
cells.

What this choice gives up:

- **a change of a tag alone is not marked**: bold made italic shows the italic text unmarked. The change list, and the
  `FieldChange`s of a document, have it;
- **a deleted element without text** — an image, a line break — is not shown, since its tag is left out;
- deleted text may land in a parent where text is unusual, such as a `<ul>` between two items; browsers render it.

## Styling

```css
ins { background: #e6ffec; text-decoration: none; }
del { background: #ffebe9; text-decoration: line-through; }
```

Blocks of a document difference carry their own classes ([documents.md](documents.md)).
