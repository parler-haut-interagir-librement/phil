# UJO documents

When `phil/ujo-bundle` is installed and `ujo.enabled` is true (the default), the bundle decorates the
`Phil\Ujo\Html\HtmlRenderer` service: `withCodeHighlighter()` gives its code blocks the highlighter. Everything that
renders through that service is coloured: `Ujo::toHtml()`, the `ujo_html` filter and function, the formats of
`phil/markup-bundle` read into UJO, and code that injects the renderer.

```twig
{{ article.body|ujo_html }}
{# <pre class="ujo-code"><code class="language-php"><span class="hl-variable">$a</span> … </code></pre> #}
```

The renderer of `phil/ujo` keeps the coloured HTML only when it holds nothing but the code's text, escaped, inside
`<span class="…">` elements; any other HTML falls back to the escaped code, so the document stays purified by
construction. A block with no language, or with one the highlighter does not know, is written as escaped text with its
`language-…` class, for the highlight controller of `phil/ux-bundle` to try in the browser; a block already coloured
on the server is left alone there.

The decoration composes with the others on the same service, such as the typographic pass of
`phil/typography-bundle`, whose typographer never changes the text of `pre` and `code`.

## Through a purifier

`phil/markup`'s `SanitizerPurifier` keeps the `hl-…` classes on `span` at the levels that keep spans, and the
`language-…` class on `code` at every level: HTML purified at `all` keeps its colours, at `forum` its text.

## Switching it off

```yaml
phil_highlight:
    ujo: false
```

The code blocks are then escaped text with their `language-…` class, as `phil/ujo` writes them. Without
`phil/ujo-bundle`, the code highlighter is removed when the container is compiled, and nothing is decorated.
