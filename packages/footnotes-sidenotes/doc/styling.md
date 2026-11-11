# Styling

`resources/css/notes.css` styles semantic HTML with classes in the manner of phil-ux, `isX` and `hasX`, in the cascade
layer `phil.components`. A site's own rules, written outside any layer, win without `!important`. Every value reads a
`--phil-*` token and falls back to a plain one.

| Class | On |
|---|---|
| `isNoteReference` | The `sup` of a reference |
| `isNotes`, `isNotesTitle`, `isNotesList` | The region of notes, its heading, its list |
| `isNote`, `isNoteMarker`, `isNoteBacklink` | A note in a list, its label, a link back |
| `hasSidenotes` | The wrapper of an output with margin notes |
| `isSidenoteAnchor`, `isSidenoteSwitch`, `isSidenoteToggle`, `isSidenote` | The group of a margin note, its checkbox, its label, its text |
| `isPageNote` | A note at the foot of a printed page |

## The margin column

From 72 rem wide, `hasSidenotes` keeps a column free with its end padding and each note floats into it, beside the line
of its reference. The width and the gap are `--sidenote-column` and `--sidenote-gap`, set on `hasSidenotes`. The float is
`inline-end`, so a right-to-left document gets its column on the left.

## Narrow viewports

Below 72 rem, each note folds under its toggle: the mark in the text is the label of a checkbox, which opens the note
under the line, and the keyboard reaches it. A link to the note, from a later reference, opens it through `:target`.

## When printing

The sheet also opens every margin note in the flow and hides the switches and backlinks under `@media print`, for a
reader who prints the page from a browser. A book goes through the [page foot placement](print.md) instead.
