# Lines

By default the HTML holds no line structure: the newlines of the source keep the lines inside `<pre>`. Given
`LineOptions`, `toHtml()` and `highlight()` write the block line by line instead, so that a theme can number the lines,
mark some of them, and show what a change adds and removes.

```php
use Phil\Highlight\Highlighter;
use Phil\Highlight\LineOptions;
use Phil\Highlight\LineRanges;

$options = LineOptions::numbered()->withHighlighted(LineRanges::parse('2'))->withAnnotations();

echo Highlighter::default()->toHtml("\$a = 1;\n\$b = 2;\n\$c = 3; // [!code ++]\n", 'php', $options);
```

```html
<span class="hl-line"><span class="hl-line-number">1</span><span class="hl-variable">$a</span> …</span>
<span class="hl-line hl-line-highlighted"><span class="hl-line-number">2</span><span class="hl-variable">$b</span> …</span>
<span class="hl-line hl-line-inserted"><span class="hl-line-number">3</span><span class="hl-variable">$c</span> …</span>
```

## The HTML of a line

| Markup | When |
|---|---|
| `<span class="hl-line">` | Every line, its tokens inside; the lines are joined by the newlines of the source |
| `hl-line-highlighted`, added | The line is in the highlighted ranges, or a `[!code highlight]` covers it |
| `hl-line-inserted`, added | A `[!code ++]` covers it |
| `hl-line-deleted`, added | A `[!code --]` covers it |
| `<span class="hl-line-number">` | First in the line, when the lines are numbered |

A line carries every mark that covers it, in the order of the table. A token that spans lines, such as a block comment,
is cut into one span per line, each of its type. A newline that ends the source ends the last line; it does not open
an empty one. The output still holds nothing but `<span class="…">` and escaped text.

## The options

`LineOptions` holds three choices, each changed alone by its `with…()` method:

| Option | Default | Effect |
|---|---|---|
| `firstNumber` | `null` | The number of the first line, from 0 to 1,000,000; `null` writes no number. `LineOptions::numbered()` starts at 1 |
| `highlighted` | none | The lines marked highlighted, a `LineRanges` |
| `annotations` | `false` | Whether the annotations of the comments are read and taken out |

The highlighted ranges count the lines by their place in the block, from 1, whatever the first number: with
`numbered(10)`, the range `2` marks the line numbered 11.

## Line ranges

`LineRanges::parse()` reads the notation of code fences and templates: lines and inclusive ranges separated by commas,
spaces and tabs allowed around each part.

```php
LineRanges::parse('1,3-5');     // lines 1, 3, 4 and 5
LineRanges::parse(' 7 - 9 , 2'); // lines 2, 7, 8 and 9
LineRanges::parse('');          // no line
LineRanges::of([3, 5], [1, 1]); // the same as '1,3-5'
```

The ranges are sorted and merged, so `toString()` gives the shortest text, which `parse()` reads back. A line is from
1 to 1,000,000, a text holds 256 parts at most, and a range ends at or after its start; anything else throws
`InvalidLineRanges`, whose message quotes at most 64 bytes of the text.

## Annotations

With `annotations`, a comment may mark its own line:

| Annotation | Mark |
|---|---|
| `[!code ++]` | Inserted |
| `[!code --]` | Deleted |
| `[!code highlight]` | Highlighted |

Followed by `:` and a number, an annotation marks that many lines from its own: `[!code highlight:3]` marks its line
and the two after it, and stops at the end of the block. Only comments are read — an annotation inside a string stays
as written —, and another word, such as `[!code focus]`, is left alone.

The annotation is taken out of the comment, with the spaces before it. A comment left with nothing but its
delimiters — `//`, `#`, `--`, `;`, `%`, `/* */`, `<!-- -->`, `{# #}`, `(* *)` — goes, with the spaces before it on
its line:

| Source | Line written |
|---|---|
| `$a = 1; // [!code ++]` | `$a = 1;`, inserted |
| `$a = 1; // why [!code ++]` | `$a = 1; // why`, inserted |
| `<p>x</p> <!-- [!code --] -->` | `<p>x</p>`, deleted |

Annotations change the text of the block. A renderer that checks the text it is given against the code, as the code
blocks of `phil/ujo` do, keeps the output of `highlight()` without `LineOptions`.

## Without a known language

`HtmlFormatter::formatLines()` takes any tokens. For a language the highlighter does not know, one plain token gives
the lines without colours:

```php
use Phil\Highlight\HtmlFormatter;
use Phil\Highlight\Token;

HtmlFormatter::formatLines([new Token($code)], LineOptions::numbered());
```

## Cost

The lines are written in one pass over the tokens, and the annotations in one pass over the lines: an annotation that
covers many lines costs no more than one that covers its own. The theme does the rest: see
[HTML and themes](html-and-themes.md#lines).
