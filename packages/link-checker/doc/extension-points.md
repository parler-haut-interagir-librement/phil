# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\LinkProbe` | `HttpLinkProbe`, or a test double | checks one address, and gives the HTML of a page when asked |
| `Contract\HostThrottle` | `IntervalThrottle`, or a shared limiter | waits until a request to a host may leave |
| `Contract\InternalPages` | the application | gives the HTML of a page of the site without a request |
| `Contract\DeadLinkFixer` | a bundle or the application | repairs a broken or redirected link of a report |

## Batches and queues

The library sends no message and needs no queue. `LinkChecker::check()` takes any iterable of `ContentLinks` and
yields one `LinkReport` per content: the batch is the unit of work, inside which each address is checked once. A bundle
or an application cuts the work where it suits it:

- a Messenger message that carries the identifiers of a few contents — identifiers only, never their HTML —, whose
  handler loads them, calls `check()` and stores the reports;
- a scheduled command that walks the contents changed since the last run, a page of a listing at a time;
- the save of one content, checked at once with `checkContent()`.

Addresses named by several batches are checked once per batch, and once per cache lifetime across batches when a
cache is given. Within a batch, the checker remembers at most 10,000 results; past them, the oldest are forgotten.

## The cache

The checker takes a `Symfony\Contracts\Cache\CacheInterface`, which every pool of `symfony/cache` implements. It keeps
the result of each external address under `phil_link_checker.` followed by the xxh128 hash of its key: the address never
appears in the cache key. A result to review is kept `reviewTtl` seconds (an hour by default), any other `ttl` seconds
(a day by default, 30 days at most); `reviewTtl` cannot exceed `ttl`.

```php
new LinkChecker($probe, cache: $pool, ttl: 7 * 86_400, reviewTtl: 6 * 3_600);
```

Pages of the site itself are never cached: they change with the site, and are cheap to ask. An entry of another shape —
written by another version, say — is ignored, and the address checked again.

## The throttle

`IntervalThrottle` keeps two requests to the same host at least `interval` seconds apart (one by default, 60 at most);
requests to different hosts do not wait for each other. It sleeps through a Symfony clock, remembers the last
`maxHosts` hosts (1,000 by default), and forgets them on `reset()`.

It paces one process. An application that checks links in several workers at once implements `HostThrottle` on a shared
store — Symfony's RateLimiter with a lock store, for instance:

```php
use Phil\LinkChecker\Contract\HostThrottle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class SharedThrottle implements HostThrottle
{
    public function __construct(private RateLimiterFactoryInterface $limiters)
    {
    }

    public function wait(string $host): void
    {
        $this->limiters->create($host)->reserve()->wait();
    }
}
```

The redirects phil/safe-http follows within one check are not paced: they are part of the same request.

## The probe

`HttpLinkProbe` takes the transport phil/safe-http wraps — Symfony's `HttpClient::create()` by default, a
`MockHttpClient` in tests —, the `Limits` of each read (time, bytes, redirects, `User-Agent`), fixed addresses for host
names, still checked against the private networks, and the throttle:

```php
new HttpLinkProbe($httpClient, new Limits(timeout: 3.0, maxRedirects: 5, userAgent: 'ExampleLinks/1.0'), throttle: $throttle);
```

Another `LinkProbe` can replace it — one that answers from a list of known addresses, say. It receives the address
without its fragment, and is asked for the HTML only for a page of the site with an anchor to find.

## The internal pages

```php
use Phil\LinkChecker\Contract\InternalPages;
use Phil\Url\Url;

final readonly class RenderedPages implements InternalPages
{
    public function __construct(private PageRenderer $renderer)
    {
    }

    public function html(Url $url): ?string
    {
        return $this->renderer->renderPath($url->path, $url->query);
    }
}
```

Null means the application cannot tell; the page is then asked over HTTP. The HTML given is read up to the bytes of the
extractor.

## The fixer

`DeadLinkFixer` is the contract of a one-click repair: `supports()` says whether a link of a report can be repaired,
`fix()` repairs it — a redirect created from a dead internal address to the page that replaced it, or the link rewritten
to its permanent target. The library calls neither: a bundle or the application offers the repair on its own screens,
phil/redirects-bundle being the natural place to create the redirect.
