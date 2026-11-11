# Print

`Placement::PageFoot` and `resources/css/notes-print.css` follow CSS Generated Content for Paged Media, for an engine
that floats footnotes, such as WeasyPrint.

| Part | In the sheet |
|---|---|
| The note | `.isPageNote { float: footnote }`, on the element written in place of its reference |
| The call in the text | `::footnote-call`, its content the attribute `data-note-label` |
| The mark in the note | `::footnote-marker`, the same attribute followed by a full stop |
| The area | `@page { @footnote { … } }`, a thin rule above the notes |

The `data-note-label` attribute carries the label of the resolver, so restarts, roman numerals and symbols print as they
were resolved, without the engine's own count.

A later reference to a note already written is a link holding its label, a `sup` of class `isNoteReference`: one note
stands on its page, and a reference on another page still names it.

## White space

The text of a note is written on one line: each run of ASCII white space becomes a single space, none is left at the
ends. A print engine may turn a new line in the source of a floated element into a forced break, cutting the note after
its first words; the same text on one line reads as a paragraph. No-break spaces, which typography puts on purpose,
stay. A `<br>` in a note remains a line break.

## Restarting on each natural page

The numbering the resolver knows is that of the document, of its chapters and of its explicit `pageBreak` blocks. Where
a page ends in the flow is known only to the print engine; for notes numbered per natural page, leave the count to it
with `resources/css/notes-print-per-page.css`, `Stylesheet::PrintPerPage`, after the print sheet:

```css
@page { counter-reset: footnote; }
.isPageNote::footnote-call { content: counter(footnote); }
.isPageNote::footnote-marker { content: counter(footnote) ". "; }
```

An engine that follows CSS GCPM starts the count again on each page. WeasyPrint 70 counts the notes before it lays out
the pages and ignores that reset: its numbers go on from page to page.
