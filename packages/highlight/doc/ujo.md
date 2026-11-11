# UJO documents

`phil/ujo` renders the code blocks of a document as `<pre class="ujo-code"><code class="language-…">`. Its
`HtmlRenderer::withCodeHighlighter()` takes a highlighter — a closure from the code and its language to HTML, or `null`
for a language it does not know — and `Highlighter::highlight()` has that shape:

```php
use Phil\Highlight\Highlighter;
use Phil\Ujo\Html\HtmlRenderer;

$renderer = HtmlRenderer::default()->withCodeHighlighter(Highlighter::default()->highlight(...));
```

Neither library depends on the other. The renderer keeps the highlighted HTML only when it holds nothing but the
code's text, escaped, inside `<span class="…">` elements; any other HTML falls back to the escaped code, so the
renderer stays purified by construction. A block with no language, or with one the highlighter does not know, is
written as escaped text with its `language-…` class, for the highlight controller of `phil/ux-bundle` to try in the
browser. Give the renderer `highlight()` without `LineOptions`: the numbers of [lines](lines.md) and the annotations
taken out change the text, and the renderer would fall back to the escaped code.

## Through a purifier

`phil/markup`'s `SanitizerPurifier` drops classes, but for those of code: `language-…` on `code` at every level, and
`hl-…` on `span` at the levels that keep spans. Highlighted HTML purified at the `all` level keeps its colours; at
`forum` the spans go and the text stays.
