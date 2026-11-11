# Accessibility

| Point | What the output does |
|---|---|
| Reference and note | A link to the note, with the role `doc-noteref` and the accessible name "Note 1", and a link back, with `doc-backlink` and "Back to reference 1" |
| A note referenced several times | One backlink per reference, each named with its occurrence |
| The notes region | A `section` with `doc-endnotes`, named by its heading through `aria-labelledby` |
| The list of notes | `role="list"` on the `ol`: the stylesheet removes its markers, which would take the list semantics away in some browsers |
| Margin notes | `role="note"` on the note, which stands in the text right after its mark, so a screen reader meets the two together |
| The narrow toggle | A real checkbox, focusable and named "Show note 1", with a visible focus ring on its label and a target of at least 24 pixels |
| Without a script | Everything works: a link, a checkbox and CSS |
| Reduced motion | The one animation of the sheet, the opening of a note, runs only under `prefers-reduced-motion: no-preference` |
| A link opened on a note | `:target` shows a note folded on a narrow viewport and marks it with the focus ring |
| Several documents on a page | Every identifier starts with a prefix; give two when one document is shown twice |

## Labels

`NoteLabels` holds the words a reader hears or sees. `NoteLabels::english()`, `french()` and `forLocale()` give the
ones the package ships; an application builds its own for another language, with `%label%` and `%occurrence%` in them.

```php
new NoteLabels('Anmerkungen', 'Anmerkung %label%', 'Zurück zu Verweis %label%', 'Zurück zu Verweis %label%, Vorkommen %occurrence%', 'Anmerkung %label% zeigen');
```
