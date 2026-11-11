# HTML and themes

## Token types

Every language writes the same 26 types, so one theme serves them all. The class of a type is `hl-` and its value.

| Type | Class | What it marks |
|---|---|---|
| `Keyword` | `hl-keyword` | Reserved words, CSS at-rules, Dockerfile instructions |
| `Type` | `hl-type` | Type names, and capitalized names in the languages that say so |
| `Builtin` | `hl-builtin` | Functions the language provides, often called without parentheses |
| `Constant` | `hl-constant` | `true`, `null`, Ruby symbols, YAML and INI booleans |
| `Number` | `hl-number` | Numbers, CSS dimensions and colours |
| `String` | `hl-string` | Quoted texts, attribute values, YAML and INI plain values |
| `Regex` | `hl-regex` | Regular expression literals |
| `Comment` | `hl-comment` | Comments, diff notes |
| `Operator` | `hl-operator` | Operators, YAML block indicators, INI's `=` |
| `Punctuation` | `hl-punctuation` | Brackets, separators, markup's angle brackets, Markdown marks |
| `Variable` | `hl-variable` | Variables with a sigil, CSS custom properties, YAML anchors and aliases, SCSS interpolations |
| `Function` | `hl-function` | Calls, macros, filters, CSS functions |
| `Meta` | `hl-meta` | Preprocessor lines, decorators, attributes, shebangs, PHP tags, doctypes, YAML tags, fence info |
| `Tag` | `hl-tag` | Markup tag names |
| `AttributeName` | `hl-attr-name` | Markup attribute names |
| `Entity` | `hl-entity` | Character references |
| `Property` | `hl-property` | CSS properties, JSON, YAML and INI keys |
| `Selector` | `hl-selector` | CSS selectors |
| `Section` | `hl-section` | INI sections, diff hunk headers |
| `Heading` | `hl-heading` | Markdown headings |
| `Emphasis` | `hl-emphasis` | Markdown emphasis |
| `Strong` | `hl-strong` | Markdown strong emphasis |
| `Link` | `hl-link` | Markdown links, images and autolinks |
| `Code` | `hl-code` | Markdown code spans and fenced blocks |
| `Inserted` | `hl-inserted` | Lines a diff adds |
| `Deleted` | `hl-deleted` | Lines a diff removes |

## The HTML

`HtmlFormatter::format()` escapes every text with `htmlspecialchars()` — `&`, `<`, `>`, `"` and `'` — and wraps each
typed token in `<span class="hl-…">`; plain text, such as spaces and ordinary names, is not wrapped. Tokens of the same
type next to each other are already joined by the tokenizer, so a run of punctuation is one span. Invalid UTF-8 is
replaced by U+FFFD. The output holds no other markup and no line structure of its own: put it inside
`<pre><code class="language-…">`, whose `white-space` keeps the lines.

The `language-…` class is what the highlight controller of `phil/ux-bundle` reads in the browser; a block already
holding `hl-` spans is left as it is, so code coloured on the server is not coloured twice.

Given `LineOptions`, the same tokens are written line by line, each line in a `<span class="hl-line">` with its marks
and its number: see [Lines](lines.md).

## The themes

`themes/light.css`, `themes/dark.css` and `themes/auto.css` set custom properties on `:root` and colour the classes
with them; `pre` holding a `code` with a language, or any element with the class `hl`, takes the theme's background
and text colour. Each colour meets the WCAG AA contrast for normal text against its background.

| Property | Colours |
|---|---|
| `--hl-background`, `--hl-text` | The block |
| `--hl-keyword` | Keywords, selectors, sections, headings |
| `--hl-type` | Types and built-ins |
| `--hl-function` | Calls, links |
| `--hl-number` | Numbers, constants, references |
| `--hl-string` | Strings, regular expressions, Markdown code |
| `--hl-comment` | Comments, in italics |
| `--hl-punctuation` | Operators and punctuation |
| `--hl-variable` | Variables |
| `--hl-meta` | Meta |
| `--hl-tag` | Tag names |
| `--hl-property` | Attribute names and properties |
| `--hl-inserted`, `--hl-inserted-background`, `--hl-deleted`, `--hl-deleted-background` | Diff lines |
| `--hl-line-highlighted-background`, `--hl-line-inserted-background`, `--hl-line-deleted-background` | Marked lines |

A page changes a colour by redefining its property, on `:root` or on one block:

```css
:root { --hl-keyword: #7c3aed; }
```

A theme of your own is a stylesheet of the `hl-` classes: the list above is the whole contract, with the classes of
the lines below.

## Lines

When a block is written line by line, each theme lays the lines out:

| Class | Laid out as |
|---|---|
| `hl-line` | A box as wide as the block at least, with room at its start for a bar and a sign |
| `hl-line-number` | Right-aligned in the colour of comments; it cannot be selected, so a copy of the block holds the code alone |
| `hl-line-highlighted` | Its tint and a bar in the colour of calls |
| `hl-line-inserted` | Its tint, a bar and a `+` in the colour of inserted lines |
| `hl-line-deleted` | Its tint, a bar and a `-` in the colour of deleted lines |

A mark is never told by colour alone: the bar and the sign say it too, and the sign is hidden from screen readers.
Every colour of a token keeps the WCAG AA contrast on the three tints; in the light theme, whose colours sit close to
that limit against its background, the tints are faint and the bar does most of the telling.
