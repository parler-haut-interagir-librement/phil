# Extension points

What an application implements, replaces or relies on.

## Languages

### `Language`

`Phil\Highlight\Language` — a language the highlighter reads. A service implementing it is tagged
`phil_highlight.language` by autoconfiguration, or by hand, and added to the highlighter: see
[Languages](languages.md).

| Method | Contract |
|---|---|
| `name(): string` | the canonical name, in lower case, letters, digits and `_+#.-`, up to 32 characters |
| `aliases(): list<string>` | other names, written the same way |
| `tokenize(string $code): list<Token>` | never throws, never loses a byte: the texts of the tokens joined are the code |

A tagged language takes its name and aliases from any language that had them, shipped or tagged earlier.

## Configuration

| Option | What it extends |
|---|---|
| `aliases` | More names for any language, shipped or the application's |
| `ujo.enabled` | Whether the UJO renderer is decorated |

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\Highlight\Highlighter` | Injected wherever code is coloured outside templates; public. An application that defines its own service under this id after the bundle keeps the tagged languages and aliases added to it |
| `Phil\Ujo\Html\HtmlRenderer` | Decorated by `phil_highlight.ujo_renderer`; other decorations of the same service compose with it |

## Theme

The custom properties `--hl-*` of [the theme](theme.md) are the contract of the stylesheet: a page redefines any of
them, on `:root` or on one block, and the classes `hl-*` of `phil/highlight` are the contract of a theme of its own.
