# Twig

When Twig is installed, the bundle adds two filters, each also a function:

| Filter or function | Gives |
|---|---|
| `ujo_html` | the HTML of a document or of its JSON, checked and purified, marked safe |
| `ujo_text` | its plain text, escaped by Twig like any text |

```twig
<article>{{ article.body|ujo_html }}</article>
<aside>{{ ujo_html(article.summary) }}</aside>
<meta name="description" content="{{ article.body|ujo_text|u.truncate(160, '…') }}">
```

The value is a `Phil\Ujo\Document`, a JSON text or `null`, which renders nothing. The document is normalized again
before it is rendered, so a stored document follows today's rules — a type removed from the registry, a tightened
sanitizer. A document that is no longer valid throws the library's exception, as any error of a template does; check
documents when they are saved, with [the field and the constraint](forms-and-validation.md), so that does not happen.

The HTML of each type and the classes it carries are described in `phil/ujo`'s `doc/html-rendering.md`.
