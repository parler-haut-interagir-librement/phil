# Routing

Published content has addresses: a **route table** holds the slug of each root in each locale, the
**`ContentController`** serves them, and old slugs redirect permanently.

## The route table

`Domain\Routing\Route` is an entity mapped to `phil_content_route`, one row per address:

| Column | Holds |
|---|---|
| `id` | `phil_content_route_id`, a UUIDv7 generated when the route is built |
| `resource_key` | the kind of content (`VARCHAR(64)`) |
| `locale` | `phil_content_locale` |
| `slug` | `VARCHAR(255)` |
| `resource_id` | the identifier of the root it points to, as a string (`VARCHAR(64)`) |
| `history` | `false` for the current route, `true` for an old slug |

It is **unique on `(resource_key, locale, slug)`**, so two kinds of content may share a slug, and one kind may not
use a slug twice in a locale; it is indexed on `(resource_key, resource_id, locale)` for the lookups by content.

`Route` exposes `current(ContentReference, Locale, Slug)` (its only constructor), `id()`, `content()`, `locale()`,
`slug()`, `isHistory()`, `retire()` and `pointTo(ContentReference)`; `pointTo()` refuses a reference of another
kind (`InvalidContent`).

### Invariants

- **Routes describe what readers are served.** They are written when a localized row is published, from its
  slug, never while it is edited. Publishing makes the slug the current route of the root in that locale; the
  previous current route becomes history.
- **An old slug redirects permanently** to the current route of the same root and locale. A history route stores
  no target: the current route is looked up when the request comes, so a chain of renames redirects once.
- **A slug comes back to its root**: publishing a slug the root used before revives its history route. A history
  route of another root of the same kind gives its slug up, and the old address then serves the new content.
- **Unpublishing keeps the route**, so the address is not given to another root; it answers 404 until the locale
  is published again.
- **Deleting a root frees its addresses.** `ContentManager::delete()` removes the routes with the rows and the
  root; a route left by a root deleted around it is free all the same, and taken over like a history route.

### `RouteUpdater`

| Method | Does |
|---|---|
| `assertAvailable(ContentReference, Locale, Slug)` | passes when the slug is free in its locale, the root's own, a history route of another root, or the route of a root that no longer exists; throws `SlugTaken` otherwise. `ContentEditor` calls it on every edit that sets a slug |
| `assign(ContentReference, Locale, Slug)` | makes the slug the current route (reviving or taking over an existing route, or adding one) and retires the previous current route; nothing happens when the slug is already current. `ContentPublisher` calls it on publication |

### `RouteResolver`

`resolve(string $resourceKey, FallbackChain $chain, string $slug): ?RouteMatch` looks the slug up in each locale of
the chain, in order: a page requested in `fr_CA` that only exists in `fr` is found by its `fr` slug.

- a current route answers `RouteMatch::serve($route)` (`$match->redirect` is `false`);
- a history route answers `RouteMatch::redirectTo($current)` with the current route of the same root and locale,
  or `null` when that root has no current route any more;
- a string that is not a valid slug, or a slug no locale of the chain holds, answers `null`.

## Mounting the controller

The application mounts `Phil\ContentBundle\Infrastructure\Http\ContentController` once per kind of content,
with a `{slug}` parameter, and names the route under `phil_content.routes`:

```yaml
# config/routes/content.yaml
news_show:
    path: /{_locale}/news/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController

page_show:
    path: /{_locale}/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
    priority: -10
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    routes:
        news: news_show
        static_page: page_show
```

The locale is the request's (`Request::getLocale()`): put `{_locale}` in the paths, or use localized routes, or —
for a site with one locale — make sure `framework.default_locale` is a locale declared under
`phil_content.locales`. A catch-all such as `/{_locale}/{slug}` is given a low priority so that the
application's other routes match first.

### Routes of a fixed address

A kind may also be mounted on routes of a fixed address, one page each, with the slug as a route default. They
are listed **after** the route the kind's URLs are generated on, which takes the `{slug}` parameter: a page keeps
the route name its templates link to, and every URL of it is still generated on the first route.

```yaml
# config/routes/content.yaml
privacy:
    path: /{_locale}/privacy
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
    defaults: { slug: privacy }
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    routes:
        static_page: [page_show, privacy]   # page_show generates; privacy serves /privacy as well
```

A route serves one kind of content only: a route listed under two keys, or twice, is refused when the container
is built. `ResourceRoutes` holds the mapping: `routeOf($resourceKey)` is the generating route,
`resourceKeyOf($route)` the kind a route serves.

## What the controller answers

The controller reads the kind of content from the route that matched (its `_canonical_route` for a localized
route) and the locale from the request, then:

1. **404** when the route serves no kind under `phil_content.routes`, or the request's locale is malformed or not
   served;
2. resolves the slug along the locale's declared chain; **404** for an unknown slug;
3. **301** to the current slug for a history slug — on the same route, or on the generating route when the route
   that matched has a fixed address;
4. reads the live content of the root along the chain; **404** when the root no longer exists or no locale of the
   chain publishes it;
5. **one content, one address**: when the locale served is not the locale whose route matched — the `fr` slug
   requested in `fr_CA`, where the root publishes `fr_CA` under a slug of its own — answers **301** to the slug of
   the locale served. A locale served without a route of its own has no address: the content is then read from
   the locale of the route that matched onward, along the rest of the chain;
6. **200**: renders the template of the row's template key, or the default template
   ([rendering.md](rendering.md)).

A redirect keeps the route's other parameters (`_locale`, …) and replaces `slug`.

A template key nobody declared, without a default template, is a mistake of the configuration: the
`LogicException` of `TemplateResolver` is not turned into a 404.

## Generating URLs

`Infrastructure\Http\ContentUrlGenerator::generate(ContentReference $content, Locale $locale, bool $absolute = false): ?string`
returns the URL of the root's current route in the locale, on the generating route of its kind, or `null` when
the root has no current route in the locale or its kind is mounted on no route.

`_locale` is passed only when it differs from the `_locale` of the request being served (the router's context),
so a site with one locale and no `{_locale}` in its paths never gets a `?_locale=` query; a site with several
mounts its routes with `{_locale}`, or as localized routes, which the generator picks by `_locale`.

In templates, the same generator answers `content_url()` and `content_link_url()`
([rendering.md](rendering.md)).
