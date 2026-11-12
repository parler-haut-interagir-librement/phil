# Declared routes

**The counted page is a declared route, never a URL.** A site declares a closed list of routes, and every path
a page view reports is normalized at ingestion into the name of the route it matches, or `(other)`.

State: `DeclaredRoute` and `DeclaredRoutes` are implemented and tested. The list of routes per site lives in
the collection projection ([collection-and-admission.md](collection-and-admission.md)): the `routes` of each site under
`phil_analytics.sites` ([configuration.md](configuration.md#the-sites-sites)), or the application's own projection.

## Why

- **The query string and the fragment are dropped** before matching. They can carry an authorization code and
  state, a password-reset token or an unsubscribe token; none of them is ever read or kept.
- **A `{placeholder}` segment is never kept.** `/u/{name}` counts every profile under one route name and keeps
  no user name.
- **A path that matches no declared route is counted under `(other)` at ingestion.** A forged path therefore
  creates no new counter, and the number of counters per site is bounded by the number of declared routes
  plus one.

## `DeclaredRoute`

```php
$route = DeclaredRoute::of('event', '/events/{slug}');
$route->name;      // 'event'
$route->pattern;   // '/events/{slug}'
```

| Rule | Detail |
|---|---|
| Name | `CounterKey::NAME_PATTERN`: 1 to 64 characters among `a-z`, `0-9`, `_`, `.`, `-`, starting with a letter. `(other)` is therefore not a valid route name |
| Pattern | Starts with `/` |
| Placeholder segment | A whole segment `{name}` where `name` matches `[A-Za-z_][A-Za-z0-9_]*`. It matches **exactly one non-empty segment** |
| Literal segment | Characters among `A-Za-z0-9._~!$&'()*+,;=:@%-`. Matches itself, **case-sensitively** |
| Refused | A partial placeholder (`x{slug}`, `{id}x`), an unclosed one (`{id`), wildcards (`*`, `?`), a trailing newline |

Other public methods:

| Method | Returns |
|---|---|
| `matches(list<string> $path): bool` | Whether the route matches these path segments (same count, literals equal, placeholders take anything) |
| `specificity(): int` | The number of literal segments, counted once when the route is built |
| `length(): int` | The number of segments a matching path has |
| `DeclaredRoute::split(string $path): list<string>` | The segments of a path, trailing slash ignored: `/` is no segment, `/a/b/` is `a`, `b` |
| `hasSameShapeAs(DeclaredRoute $other): bool` | Whether two routes match exactly the same paths |

## `DeclaredRoutes`

```php
$routes = DeclaredRoutes::of([
    DeclaredRoute::of('home', '/'),
    DeclaredRoute::of('event', '/events/{slug}'),
    DeclaredRoute::of('events.archive', '/events/archive'),
    DeclaredRoute::of('profile', '/u/{name}'),
]);

$routes->normalize('/events/spring-con');                 // 'event'
$routes->normalize('/events/spring-con/');                // 'event' (trailing slash ignored)
$routes->normalize('/events/archive');                    // 'events.archive' (more literals win)
$routes->normalize('/events/spring-con?code=abc&state=x');// 'event' (query dropped)
$routes->normalize('/u/alice#token');                     // 'profile' (fragment dropped, name not kept)
$routes->normalize('/admin');                             // '(other)'
$routes->normalize('/events/spring-con/tickets');         // '(other)' (too deep)
$routes->normalize('/Events/archive');                    // '(other)' (literals are case-sensitive)
$routes->normalize('/events//x');                         // '(other)' (an empty segment never fills a placeholder)
$routes->normalize('events/x');                           // '(other)' (not an absolute path)

DeclaredRoutes::none()->normalize('/');                   // '(other)'
$routes->routes;                                          // list<DeclaredRoute>, in declaration order
```

Normalization rules, in order:

1. Everything from the first `?` or `#` is dropped.
2. A path that does not start with `/` is `(other)`.
3. A path with an empty segment (`//`) is `(other)`.
4. Among the routes that match, the one with the most literal segments wins; on a tie, the first declared. Only the
   routes with as many segments as the path are tried: `DeclaredRoutes` keeps them by segment count, so a page view
   costs the routes of its own length, not every declared route.
5. No match is `(other)`.

`DeclaredRoutes::of()` refuses a route name declared twice, and two routes of the same shape (`/x/{id}` and
`/x/{slug}`), which would match exactly the same paths.

The returned name is used directly as a dimension value:

```php
$store->increment(CounterKey::of($site, $day, 'pageviews', 'route', $collectionSite->routes->normalize($path)));
```
