# Tagging

A tag is 1 to 128 letters, digits, `_`, `.`, `:` and `-`: `page:42`, `product-list`.

| From | How |
|---|---|
| A controller | `#[CacheTag('page:{id}', 'menu')]` on the method or the class, repeatable; `{name}` is the request attribute — a route parameter —, and a tag whose attribute is missing, or whose value makes it no tag, is left out |
| A template | `{{ cache_tag('product:' ~ product.id) }}`, which writes nothing |
| Code | `ResponseTags::add('page:42')` |

When the main response leaves, its tags are written into the configured header, each once, in the order they came
— `xkey: page:42 menu`, or `Cache-Tag: page:42,menu` —, then forgotten for the next request. The header is written
late, after Symfony's `SessionListener` and before its `ProfilerListener`, so a `kernel.response` listener of the
application that runs before the session listener may still add tags. A sub-request's tags
stay for the main response, so that a fragment tags the page it is part of. A response without tags gets no header.

A route parameter comes from the URL: `GET /page/café` gives `{slug}` the value `café`, and `page:café` is no tag.
Such a tag is left out rather than turned into an error, so the page is served without it: tag a page whose slug may
hold accents or spaces by its identifier, `page:{id}`, which is also the tag a `CacheTagResolver` gives when the page
changes. A tag written without a placeholder is the developer's, and one that is not a tag is an `InvalidCacheTag`.

The cache in front keeps the response by its own rules: give it a `Cache-Control: public, s-maxage=…`.

The header names the identifiers of what the page shows — `user:4812`, `order:99173` — and the bundle writes it on
every tagged response: the cache in front must remove it before the response reaches the client. Varnish's xkey
module keeps it, so the VCL ends `vcl_deliver` with `unset resp.http.xkey;`; a CDN that reads `Cache-Tag` or
`Surrogate-Key` usually strips it, but check. Keep the tags of one response few — a list tags itself `product-list`
rather than each of its entities —, since a header past about 8 KB makes nginx or Varnish answer with a 502.
