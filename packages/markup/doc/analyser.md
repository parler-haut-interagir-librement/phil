# The analyser

The readers are written on one analyser, in the manner of a hand-written lexer: a cursor that moves forward through
the text and rules that lex one kind of token each, without regular expressions where a cursor can do the work.

## The cursor

`Lexer\Cursor` reads a text byte by byte; the characters of every syntax are ASCII, and a character of several bytes
is consumed whole by the methods that consume runs.

| Method | What it does |
|---|---|
| `current()`, `peek($offset)` | The byte at the position, or at an offset — negative to look back — `''` outside the text |
| `comesNext($text)` | Whether the text at the position starts with this one |
| `consume($text)` | Moves past the text when it comes next |
| `consumeWhile($characters)`, `consumeUntil($characters)` | The run of bytes among, or not among, the characters, consumed |
| `consumeThrough($delimiter)` | The text up to a delimiter, consumed with it; `null`, and nothing consumed, when it never comes |
| `advance($length)`, `rest()` | Bytes, or all that is left, consumed |
| `position()`, `moveTo($position)` | Where the cursor is; how a rule that does not match gives the text back |

`Lexer\Slugger` makes the anchors of one document, unique, as GitHub writes them; it remembers the suffix each text
reached, so that many headings of the same text take constant time each.

## Two passes

Markdown is read in two passes, so that a link reference, a footnote or a variable may be defined after the text that
uses it.

1. **Blocks.** `BlockParser` reads the lines. A container gathers its lines — those of a quote without their `>`,
   those of a list item without their indentation, those between `{{` and `}}` — and reads them as a document of its
   own. Each block is a UJO block whose inline content is not read yet: an `Inline` of Markdown pieces.
2. **Inline content.** `Resolver` reads every `Inline` with `InlineParser`, splits paragraphs around their images,
   gives headings their anchors, and adds the footnotes referenced at the end.

`InlineParser` lexes with one rule per kind of token, each started by its own characters; the text between tokens is
escaped. The delimiter runs of emphasis are kept as tokens, then paired as CommonMark pairs them: from the first
closer, with the nearest opener of the same character.

| Rule | Starts with | Lexes |
|---|---|---|
| `EscapeRule` | `\` | an escaped punctuation character, a line break |
| `EntityRule` | `&` | an HTML entity |
| `CodeSpanRule` | `` ` `` | a code span |
| `AngleRule` | `<` | an autolink, or raw HTML |
| `LinkRule` | `[`, `!` | a link, an image, a footnote reference |
| `BareLinkRule` | `h`, `w` | an address without brackets |
| `DelimiterRule` | `*`, `_`, `~`, `=`; `^` for a dialect that adds it | a delimiter run, and whether it opens or closes |

The rules of a `MarkdownDialect` come after these, on their own characters: see
[Extension points](extension-points.md#dialects-of-markdown).

BBCode is read in two passes too. `Reader\BBCode\Parser` lexes the tags with the cursor into a tree, closing what
is left open; `Builder` makes the tree into UJO blocks whose inline content is HTML already, and the `Resolver` of
the Markdown reader splits paragraphs around images, gives headings their anchors and adds the footnotes. The boxes,
stat blocks and page styles are those of the Markdown reader, made by its `Curly`. See [BBCode](bbcode.md).

The wikis share one reader, `Reader\Wiki`, each wiki giving it its syntax. `Containers` sets the tags of containers
on lines of their own in one pass; `Engine` reads the lines into blocks — the containers and the tags of pages it
knows, the other blocks asked of the wiki's syntax, paragraphs —; `Spans` reads inline text with the cursor, the
characters of the wiki lexed by its syntax, the marks of emphasis paired as they come. The `Resolver` of the Markdown
reader then places images, gives headings their anchors and adds the footnotes. See [The wikis](wikis.md). The HTML
reader walks the tree the HTML5 parser of PHP builds; see [HTML](html.md).

## Limits

A text from anyone is read in time linear in its length. Containers nest five deep, brackets of a link 32 deep,
parentheses of an address 32 deep and `{{ }}` spans 8 deep; deeper, the markers are text. As in CommonMark, a
search for an opener that failed is not made again below the closer it failed for. The second pass replaces image
marks by splitting the HTML once, and adds the footnotes in one pass over them. BBCode tags nest 32 deep; its limits
are in [BBCode](bbcode.md).
