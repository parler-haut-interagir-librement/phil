# phil/http-cache-tags-bundle

**Cache whole pages in Varnish or a CDN, and purge exactly the ones an edit changes.**

phil/http-cache-tags-bundle tags a Symfony application's responses for the HTTP cache in front of it. A response
gathers tags while it is rendered: from a controller's `#[CacheTag]`, a template's `cache_tag()`, or `ResponseTags` in
code. The tags are written into the header the cache reads: `xkey` for Varnish, `Cache-Tag` for Cloudflare,
`Surrogate-Key` for Fastly. When an entity changes in a Doctrine flush, or when the application says so, its tags are
purged once the request or the command ends. The bundle purges Varnish and Cloudflare; for Fastly it writes the header
only. It does not set how long a response is cached: the `Cache-Control` header stays the application's.

It is for a site that serves most pages from a cache and wants an edit to show at once, without purging everything.
A page tagged `page:42` and `menu` leaves the cache when that page or the menu changes, and only then. A fragment's
tags reach the page it is part of, so an embedded block is purged along with every page that shows it.

The bundle plugs into Symfony's HTTP kernel to write the header and to purge after the response is sent. Doctrine
ORM, Twig and Symfony's HTTP client are optional, for flush invalidation, `cache_tag()` and the purgers. Two ports
carry the design: a `CacheTagResolver` names the tags of the responses that show an entity, and a `CacheTagPurger`
sends the purge to one cache.

## Status

| Part | State |
|---|---|
| `CacheTag`, `ResponseTags`, the header on the main response | Implemented and tested |
| `#[CacheTag]` with route parameters; `cache_tag()` in Twig | Implemented and tested |
| `Invalidator`, `CacheTagResolver`, invalidation at each flush, purge at the end | Implemented and tested |
| `VarnishPurger` (xkey), `CloudflarePurger` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration. |
| [Tagging](doc/tagging.md) | The attribute, the Twig function, the service, the header. |
| [Purging](doc/purging.md) | Resolvers, the flush, the end of a request, Varnish and Cloudflare. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
#[CacheTag('page:{id}', 'menu')]
public function show(string $id): Response { /* … */ }
```

```twig
{{ cache_tag('product:' ~ product.id) }}
```

```php
$invalidator->invalidate('menu');   // purged when the request ends
```

## Requirements

PHP 8.5 or later; Symfony 8.2's dependency injection, config and HTTP kernel. Doctrine ORM, Symfony's HTTP client
and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
