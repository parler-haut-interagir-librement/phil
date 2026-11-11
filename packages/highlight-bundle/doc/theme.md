# Theme

`assets/styles/highlight.css` colours the `hl-` classes of `phil/highlight` and lays out the blocks that hold them:
a `pre` holding a `code` with a `language-…` class, the code blocks of UJO documents (`pre.ujo-code`), and any element
with the class `hl`. The block scrolls sideways rather than wrapping, keeps tabs four columns wide, and shows a focus
ring when it is reached by keyboard.

It is light or dark as the system prefers, unless an ancestor carries `data-theme="light"` or `data-theme="dark"`,
the attribute PHIL UX switches; it is light on paper, where long lines wrap rather than being cut. In forced colours,
the system's colours win and the lines of a diff are underlined or struck through instead of tinted. Every colour
meets the WCAG AA contrast for normal text against the background it is shown on, the tinted lines of a diff and
the marked lines included.

When the filter writes the block line by line, each line is a box as wide as the block at least; its number, in the
colour of comments, cannot be selected, so a copy holds the code alone. A highlighted line takes its tint and a bar in
the colour of calls; an inserted or a deleted line its tint, a bar and a `+` or a `-`, hidden from screen readers, so
that no mark is told by colour alone. In forced colours, the bar of a marked line takes the system's highlight colour
and the tint goes.

## Colours

Each colour is a custom property, set on `:root` and on any element with `data-theme`:

| Property | Colours |
|---|---|
| `--hl-background`, `--hl-text`, `--hl-border` | The block |
| `--hl-keyword` | Keywords, selectors, sections, headings |
| `--hl-type` | Types and built-ins |
| `--hl-function` | Calls, links, the focus ring |
| `--hl-number` | Numbers, constants, character references |
| `--hl-string` | Strings, regular expressions, Markdown code |
| `--hl-comment` | Comments, in italics |
| `--hl-punctuation` | Operators and punctuation |
| `--hl-variable` | Variables |
| `--hl-meta` | Meta |
| `--hl-tag` | Tag names |
| `--hl-property` | Attribute names and properties |
| `--hl-inserted`, `--hl-inserted-background`, `--hl-deleted`, `--hl-deleted-background` | Diff lines |
| `--hl-line-highlighted-background`, `--hl-line-inserted-background`, `--hl-line-deleted-background` | Marked lines, when the block is written line by line |

A page changes a colour by redefining its property, on `:root` or on one block, in a stylesheet loaded after the
theme:

```css
:root { --hl-keyword: #7a1fa2; }
.docs pre.ujo-code { --hl-background: #ffffff; }
```

The properties are those of the themes of `phil/highlight`, so a page that redefines them keeps its colours with
either. A theme of the application's own is a stylesheet of the `hl-` classes, which `phil/highlight` lists.
