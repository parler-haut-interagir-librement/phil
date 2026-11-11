# Extension points

The library is plain PHP and declares no service. What an application adds is languages, and what it relies on is
the shape of the HTML.

## `Language`

`Phil\Highlight\Language` — a language the highlighter reads, added by `Highlighter::with()` under its name and
aliases, in place of any language that had them. See [Adding a language](extending.md).

| Method | Contract |
|---|---|
| `name(): string` | The canonical name, in lower case: letters, digits and `_+#.-`, up to 32 characters |
| `aliases(): list<string>` | Other names, written the same way |
| `tokenize(string $code): list<Token>` | Never throws and never loses a byte: the texts of the tokens joined are the code |

A programming language is most often a `Language\Syntax` read by `Language\Code`, without a class of its own; a
language of another shape implements `Language` itself.

## Highlighters

| Point | How |
|---|---|
| Which languages are known | `Highlighter::default()` for every shipped language, `Highlighter::of()` for a chosen few, then `with()` for the application's |
| Replacing a shipped language | `with()` a language that has its name |
| How a block is laid out | `LineOptions` on `toHtml()` or `highlight()`: numbers, highlighted ranges, annotations — see [Lines](lines.md) |
| Where the HTML goes | `highlight()` has the shape of `phil/ujo`'s `HtmlRenderer::withCodeHighlighter()`: code blocks of UJO documents are coloured through it — see [UJO documents](ujo.md) |

## The HTML and the themes

The classes `hl-*` of the spans, one per `TokenType`, are the contract of a theme: a stylesheet of the application's
colours them as it likes — see [HTML and themes](html-and-themes.md). The token types are a closed enum: a language
maps what it reads onto them.
