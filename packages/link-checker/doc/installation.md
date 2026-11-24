# Installation

## Requirements

- PHP 8.5 or later, with `ext-dom` (the HTML5 parser, `Dom\HTMLDocument`), `ext-mbstring` and `ext-uri` (the WHATWG
  URL parser).
- phil/safe-http, which makes the requests, and phil/url, which reads the addresses.
- Symfony's HttpClient, Clock, and the cache and service contracts. A cache is optional: any pool of `symfony/cache`
  implements the contract the checker takes.

## Installing

```sh
composer require phil/link-checker
```

The library has no configuration file. `LinkChecker`, `LinkExtractor` and `UrlNormalizer` keep nothing between two
calls; `HttpLinkProbe` and `IntervalThrottle` implement Symfony's `ResetInterface`, which a long-running worker calls
between messages. A framework registers them as services, or the code builds them where it needs them.

## A first check

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Phil\SafeHttp\Limits;
use Phil\Url\Url;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$probe = new HttpLinkProbe(
    limits: new Limits(timeout: 5.0, userAgent: 'ExampleLinks/1.0 (+https://www.example.com/contact)'),
    throttle: new IntervalThrottle(interval: 2.0),
);

$checker = new LinkChecker(
    $probe,
    cache: new FilesystemAdapter('links'),
    internalHosts: ['www.example.com', 'example.com'],
);

foreach ($checker->check($contents) as $report) {
    // $contents is an iterable of ContentLinks: the id of a content, the address of its page, its HTML.
    foreach ($report->broken() as $link) {
        // $link->link->url, $link->link->element, $link->link->attribute, $link->link->text,
        // $link->result->status, $link->result->code, $link->result->target, $link->result->reason
    }
}
```

A content is described by a `ContentLinks`:

```php
new ContentLinks('article-42', Url::fromString('https://www.example.com/news/42'), $article->html());
```

Its relative links resolve against the page's address, or against the first `<base href>` of the HTML. `checkContent()`
checks one content; `check()` takes any iterable and yields one report per content, in order, as soon as it is ready.

[Checking links](checking.md) describes each step, and [Extension points](extension-points.md) shows how a bundle or an
application spreads the work over a queue.
