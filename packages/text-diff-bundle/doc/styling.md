# Styling

`assets/styles/text-diff.css` styles a difference inside an element with the class `text-diff`, or under the
`comparison` controller, and nowhere else:

| What | How |
|---|---|
| `ins` | Green background and text, underlined |
| `del` | Red background and text, struck through |
| `.text-diff-block` | A block of a document difference, with a border on its starting side: green when added, red when removed, blue when changed, amber with a background when moved |
| `[data-text-diff-mode="old"]` | Hides the insertions and the added blocks |
| `[data-text-diff-mode="new"]` | Hides the deletions and the removed blocks |
| `.text-diff-current` | An outline around the current change |
| `[aria-pressed="true"]` | The button of the view shown, in bold |

Insertions and deletions are told apart by their decoration as well as their colour, so that no change depends on
colour alone.

## Themes

The colours are custom properties, light by default and dark when the system prefers it, unless an ancestor carries
`data-theme="light"` or `data-theme="dark"`; on paper they are the light ones and the outline is dropped. Each meets
the WCAG AA contrast for normal text against the background it is shown on.

| Property | Role |
|---|---|
| `--text-diff-inserted`, `--text-diff-inserted-background` | Insertions and added blocks |
| `--text-diff-deleted`, `--text-diff-deleted-background` | Deletions and removed blocks |
| `--text-diff-moved`, `--text-diff-moved-background` | Moved blocks |
| `--text-diff-changed` | The border of changed blocks |
| `--text-diff-focus` | The outline of the current change |

A page redefines any of them on `:root`, or on one difference:

```css
.revision-history {
    --text-diff-inserted-background: #dff6dd;
}
```
