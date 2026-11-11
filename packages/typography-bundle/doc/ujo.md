# UJO documents

When `phil/ujo-bundle` is installed and `ujo.enabled` is true (the default), the bundle decorates the
`Phil\Ujo\Html\HtmlRenderer` service: its `withFilter()` adds a `TypographyHtmlFilter`, which runs the typographer of
`ujo.locale` on the HTML of each document; when it is null, the current locale, as the [Twig filters](twig.md#the-locale-of-a-template)
take it from Symfony's `LocaleSwitcher`. Everything that renders through that
service is fixed: `Ujo::toHtml()`, the `ujo_html` filter and function, and code that injects the renderer.

```twig
{{ article.body|ujo_html }}
{# <p class="ujo-paragraph">«&nbsp;Bonjour&nbsp;»&nbsp;: voici…</p> #}
```

The filter receives the purified HTML of `phil/ujo` and rewrites its text only, so the HTML stays as purified as it
was. The code of `code` blocks, the content of `raw` blocks inside `pre`, and every other protected element keep
their text.

A document has no locale of its own: the page's serves every document it shows. An application whose documents are
not in the language of the page disables the pass and fixes each document with the typographer of its language:

```yaml
phil_typography:
    ujo: false
```

```twig
{{ article.body|ujo_html|typography(article.locale) }}
```

Without `phil/ujo-bundle`, the filter service is removed when the container is compiled, and nothing is decorated.
