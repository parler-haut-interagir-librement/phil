# Twig

| Function | Gives |
|---|---|
| `structured_data(nonce = null)` | `<script type="application/ld+json">…</script>` with every node of the page; empty when the page has none or shows a record withdrawn from search engines, and then no nonce is asked for |
| `structured_data_add(node, …)` | Nothing: adds `Node` values, or schema.org data as maps, to the page |
| `structured_data_about(record, …)` | Nothing: names the records the page shows, whose owner may have withdrawn them from search engines |

```twig
{% do structured_data_add({'@type': 'Person', 'name': 'Ada'}) %}
{% do structured_data_add(menu_breadcrumb_schema('main')) %}

{{ structured_data() }}
```

Print it once, at the end of the body of the base layout, after the content has added its nodes.

A map is read as `Node::fromArray()` reads it: its `@context` is dropped, and the maps nested in it become nodes,
references or language strings.

## The script element

The element is written by `JsonLd::script()` of phil/structured-data: no value can close it or open a comment in it.
It is a data block, which a content security policy does not execute: by default it has no nonce, and a page that
prints it keeps its shared cache. A nonce is the choice of the site, in order:

1. the one given to `structured_data()`;
2. the first a `ScriptNonceProvider` registered by the site gives;
3. the request attribute named by `nonce.request_attribute`, or the script nonce of phil/browser-security-bundle's
   policy with `nonce.content_security_policy`;
4. none.

No nonce is taken by default, so that a page keeps its shared cache.

A nonce is base64 text; anything else stops the rendering rather than being escaped.

## A record withdrawn from search engines

A record that implements `HasIndexingConsent` of phil/indexing-consent tells whether its owner lets search engines
find it. A template that shows one names it, and the page then prints no structured data unless every record named
allows `Reader::StructuredData`:

```twig
{% do structured_data_about(profile) %}
```

See [The graph of a page](graph.md) for the controller's side.

## Other packages

phil/contact-bundle's `contact_json_ld()` and phil/calendar-bundle's `calendar_event_json_ld()` add their node to the
page and print nothing when this bundle is installed, so that the page prints one element. Render `structured_data()`
after them.
