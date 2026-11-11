# Placements

`NotesRenderer::render()` takes the document, its `Resolution`, a `Placement`, and the options of the output.

```php
$renderer->render($document, $notes, Placement::EndOfSection, idPrefix: 'part-2', sectionLevel: 2, headingLevel: 3);
```

| Argument | Meaning |
|---|---|
| `$placement` | Where the notes go, `EndOfDocument` by default |
| `$idPrefix` | The start of every identifier; derived from the blocks by default |
| `$sectionLevel` | For `EndOfSection`, the deepest level of a top-level `header` that starts a section, 2 by default |
| `$headingLevel` | The level of the title of a notes region, 2 by default |

The blocks are drawn by the `HtmlRenderer` given to the constructor, with its renderers and filters; the note blocks are
not drawn where they stand. A block reading the whole document, such as a table of contents, still sees all of it.

## End of the document

One region after the last block, the notes in the order of their first reference.

```html
<p>Text<sup class="isNoteReference"><a id="doc-ref-a-1" href="#doc-note-a" role="doc-noteref" aria-label="Note 1">1</a></sup></p>
<section id="doc-notes-0" class="isNotes" role="doc-endnotes" aria-labelledby="doc-notes-0-title">
  <h2 id="doc-notes-0-title" class="isNotesTitle">Notes</h2>
  <ol class="isNotesList" role="list">
    <li id="doc-note-a" class="isNote"><span class="isNoteMarker">1</span> The note
      <a class="isNoteBacklink" href="#doc-ref-a-1" role="doc-backlink" aria-label="Back to reference 1">↩︎</a></li>
  </ol>
</section>
```

A note referenced twice has two backlinks, each followed by its rank. With `Restart::Chapter` and one region at the end,
the same number may stand on two notes: pair a restart with the end of the section, or with a placement in flow.

## End of each section

A section starts at each top-level `header` of `$sectionLevel` or above. A note goes to the section of its first
reference; a section whose text references no note gets no region. A heading nested in a container does not start a
section, since a region cannot be placed inside it.

## Margin

The note is written where it is first referenced, in phrasing content, valid inside a paragraph. The whole output is
wrapped in `<div class="hasSidenotes">`, which keeps the column free on wide viewports.

```html
<span class="isSidenoteAnchor">
  <input type="checkbox" class="isSidenoteSwitch" id="doc-toggle-a" aria-label="Show note 1">
  <label class="isSidenoteToggle" for="doc-toggle-a"><sup class="isNoteReference">1</sup></label>
  <small id="doc-note-a" class="isSidenote" role="note"><span class="isNoteMarker">1</span> The note</small>
</span>
```

A later reference to the same note is a link to it. See [Styling](styling.md).

## Page foot

The note is written where it is first referenced, as the element GCPM floats to the foot of the page. See
[Print](print.md).

```html
<span id="doc-note-a" class="isPageNote" data-note-label="1">The note</span>
```

## A reference inside a link

HTML does not nest links. A reference inside a link is written without one, and a note written in place there — in the
margin or at the foot of the page — loses its own links, keeping their text.
