# Twig

Both filters return HTML marked safe, with `<ins>` around what was inserted and `<del>` around what was deleted. The
elements carry no attribute; [Styling](styling.md) colours them inside an element with the class `text-diff`, or under
the `comparison` controller.

## text_diff

```twig
{{ old|text_diff(new) }}              {# by the configured tokenizer #}
{{ old|text_diff(new, 'lines') }}     {# by words, lines, paragraphs or html #}
```

`null` on either side is an empty text. A tokenizer name outside the four throws an `InvalidArgumentException`.

**Plain text** — `words`, `lines`, `paragraphs` — is always escaped, the marks included. Line breaks are kept: show the
difference in an element styled `white-space: pre-wrap`, or in a `<pre>`:

```twig
<pre class="text-diff">{{ before.summary|text_diff(after.summary, 'lines') }}</pre>
```

**HTML** — `html` — is compared by words without ever cutting a tag, and the result keeps the structure of the new
HTML. Since its tags are written as they are, the filter reads as markup only what it can trust:

| The value | Read as |
|---|---|
| A `Twig\Markup`: the output of a macro, a block captured by `{% set %}…{% endset %}`, a value the application wrapped | Markup, as it is |
| Any other value, with `html_sanitizer` configured | Markup, once the sanitizer has cleaned it |
| Any other value, without a sanitizer | Text: escaped first, so that its tags are shown and never run |

```twig
{% set before %}{{ revision.html|raw }}{% endset %}
{{ before|text_diff(current.html, 'html') }}
```

Here `before` is a `Markup` and is trusted; `current.html` goes through the sanitizer, or is escaped. The `raw` filter
alone marks nothing at runtime: it only tells Twig not to escape what it prints.

## text_diff_document

```twig
{{ previous.body|text_diff_document(current.body) }}
```

Each side is a `Phil\Ujo\Document` or its JSON. The documents are read by the `Ujo` service of `phil/ujo-bundle`, and
each block rendered by its `HtmlRenderer` — the application's block types, colours and filters included —; without
that bundle, by the defaults of `phil/ujo`. Blocks are paired by identifier at every level:

```html
<div class="text-diff-block text-diff-changed"><p class="ujo-paragraph">Hello <ins>big </ins>world</p></div>
<div class="text-diff-block text-diff-added"><ins><figure class="ujo-quote">…</figure></ins></div>
```

Each top-level block is a `<div class="text-diff-block text-diff-<kind>">`, the kind being `unchanged`, `changed`,
`added`, `removed` or `moved`. The rendered HTML is the renderer's, sanitized as it always is, so the comparison
trusts it.
