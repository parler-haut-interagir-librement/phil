# Twig

| Filter | Arguments | Gives |
|---|---|---|
| `markup_html` | the format — `default_format` by default —, the level — `default_level` by default | the source compiled and purified |
| `markup_purify` | the level — `default_level` by default | stored HTML, or the HTML of a `MarkedUpText`, purified again |
| `markup_ujo` | the format, the level, with the same defaults | the UJO JSON of the source |

```twig
{{ comment.source|markup_html('text', 'basic') }}
{{ message.source|markup_html('bbcode', 'forum') }}
{{ article.body|markup_purify('all') }}
<div data-blocks="{{ draft.source|markup_ujo(draft.format.value) }}"></div>
```

`markup_html` and `markup_purify` are safe for HTML: Twig does not escape what they give. `markup_ujo` gives JSON, which
Twig escapes as any text, for an attribute or a script. A `null` gives an empty string, or a document without blocks
for `markup_ujo`.

The level asked is lowered to the ceiling of the format the [configuration](configuration.md) sets in `levels`: with
`bbcode: forum`, `markup_html('bbcode', 'all')` purifies at `forum`. `markup_purify` applies the ceiling of a
`MarkedUpText`'s format, enabled or not, and none to plain HTML, whose format it does not know. A format not enabled
throws `UnavailableFormat`, an unknown level a `ValueError`, a UJO source that is not valid `InvalidSource`: see
[Exceptions](exceptions.md).

## What a render costs

`markup_html` compiles the source and purifies the result on every render, and caches nothing: about 4 ms for ten
paragraphs of Markdown, so a thread of 50 posts spends some 200 ms in the filter. `markup_purify` only purifies, about
seven times less for the same Markdown post. `markup_ujo` reads the source and writes its JSON, about two thirds of
`markup_html`. A page that lists many texts compiles each one once, when it is written: store the `MarkedUpText` that
`Markup::text()` gives — an embeddable, its source, format and HTML together — on the entity, and print it with
`markup_purify`, which purifies it again with the level asked, so a stricter policy still applies to what was stored.
Keep `markup_html` for a preview, or a text shown once, and `markup_ujo` for the moment a text is opened in a block
editor.
