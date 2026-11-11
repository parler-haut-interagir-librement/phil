# Twig

The filter is registered when Twig is installed.

| Filter | Input | Output |
|---|---|---|
| `highlight(language = null, lines = null)` | Code, as text | The code escaped, wrapped in `hl-` spans, marked safe |

```twig
<pre><code class="language-php">{{ snippet|highlight('php') }}</code></pre>
```

The filter gives what goes inside `<code>`: no `pre`, no `code`, no line structure of its own. `white-space: pre`,
which the browser gives `pre` and the [theme](theme.md) keeps, holds the lines. The `language-…` class lets the
highlight controller of `phil/ux-bundle` know the block is code, and the theme lay it out.

## Escaping

The code is always text. Every character is escaped — `&`, `<`, `>`, `"` and `'` —, invalid UTF-8 becomes U+FFFD,
and the only markup in the result is the spans of the highlighter. A string marked safe, by `|raw` or another
filter, is read as code all the same: its tags are shown, never run.

| Language | Result |
|---|---|
| One the highlighter reads, in any case: `php`, `PHP`, `js` | The code coloured |
| One it does not know, or none | The code escaped, without spans |
| Any, on `null` | An empty string |

```twig
<pre><code class="language-{{ block.language }}">{{ block.code|highlight(block.language) }}</code></pre>
```

## Lines

`lines` writes the block line by line, each line in a `<span class="hl-line">`, as `phil/highlight` describes in its
documentation of lines:

```twig
<pre><code class="language-php">{{ snippet|highlight('php', lines: {numbers: true, highlight: '2,4-5', annotations: true}) }}</code></pre>
```

| Key | Value | Effect |
|---|---|---|
| `numbers` | `true`, `false` or the number of the first line | Each line numbered, from 1 or from the number given |
| `highlight` | Line ranges, such as `'1,3-5'` | Those lines marked highlighted, counted from 1 in the block |
| `annotations` | `true` or `false` | The `[!code ++]`, `[!code --]` and `[!code highlight]` of the comments read and taken out |

An empty hash writes the lines with no number nor mark; a `LineOptions` built in PHP is taken as it is. A language
the highlighter does not know, or none, gives the lines escaped, without colours, numbered and marked all the same. A
key the filter does not read, a value of the wrong type or ranges it cannot read throw, naming what is wrong: the
options are written by the author of the template, not by its readers.
