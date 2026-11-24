# phil/feed-bundle

**Atom, RSS and JSON Feed for news, contents or events, written from one list, linked from pages, cached over HTTP
and announced to WebSub hubs.**

phil/feed-bundle serves the feeds of a Symfony application. A feed is a service that implements `FeedSource`: a name,
a channel — title, page, description, language — and its entries, the most recent first. The bundle writes each feed
in three formats from that one list: Atom (RFC 4287), JSON Feed 1.1 and RSS 2.0 for the readers that read nothing
else. Each is served at `/feeds/{name}.atom`, `.json` and `.rss`, with its media type, an `ETag` and a `Last-Modified`
from its latest entry, and a `304` to a reader that already holds it. A paged feed links its pages as RFC 5005 asks.
`phil_feed_links()` writes the `<link rel="alternate">` of the feeds in the `<head>` of a page. When a page of
`phil/publishing` goes online or offline, the feeds that list it are announced to the configured WebSub hubs, through
Messenger and `phil/safe-http`, with a message that names the feed alone. The published contents of a kind of
`phil/content-bundle` become a feed with a few lines of configuration.

Use it in a site that publishes news, articles, events or episodes and wants readers, podcast apps and other sites to
follow them. A source of another kind — a calendar, the private feed of a partner — implements `FeedSource` and is
served the same way.

**What it does not do**: no WebSub hub of its own (the application names a public one), no reading of other sites'
feeds, no digest of new entries by e-mail.

It plugs into the Symfony router, HttpKernel, Messenger, the event dispatcher and Twig, and uses `phil/url` for every
address. The XML is written by `XMLWriter`, the JSON by `json_encode()`; no feed library is used.

## Status

| Part | State |
|---|---|
| `FeedSource`, the entries and their checks | Implemented and tested |
| Atom, RSS 2.0 and JSON Feed 1.1 writers | Implemented, tested and fuzzed |
| `/feeds/{name}.{format}`: media types, `ETag`, `Last-Modified`, `304`, cache headers, pages | Implemented and tested |
| `phil_feed_links()` | Implemented and tested |
| WebSub announcements on `PagePublished` and `PageRetired` | Implemented and tested |
| Content feeds of `phil/content-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the routes and the Flex recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Sources](doc/sources.md) | Writing a `FeedSource`: the channel, the entries, the pages, the pages of `phil/publishing`; the content feeds. |
| [WebSub](doc/websub.md) | Hubs, the announcement, Messenger and what is refused. |
| [Extension points](doc/extension-points.md) | The sources, the writers, the addresses, the routes and what stays closed. |
| [Testing](doc/testing.md) | The bundle's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every class and service of the bundle, and the formats as written. |

## Quick start

```php
// config/bundles.php
Phil\FeedBundle\PhilFeedBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_feed.yaml
phil_feed:
    resource: '@PhilFeedBundle/config/routes.php'
```

```php
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

final readonly class NewsFeed implements FeedSource
{
    public function __construct(private NewsRepository $news) {}

    public function name(): string { return 'news'; }

    public function channel(): Channel
    {
        return new Channel('News', Url::fromString('https://example.org/news'), language: 'en');
    }

    public function items(FeedQuery $query): FeedPage
    {
        $items = array_map(static fn (News $news): FeedItem => new FeedItem(
            'tag:example.org,2026:news-'.$news->id,
            $news->title,
            Url::fromString($news->url),
            $news->publishedAt,
            summary: $news->summary,
            contentHtml: $news->html,
        ), $this->news->latest($query->offset(), $query->limit + 1));

        return new FeedPage($items);
    }

    public function includes(PageEvent $page): bool
    {
        return str_starts_with($page->ownerId, 'news:');
    }
}
```

```twig
{# in the <head> of the layout #}
{{ phil_feed_links() }}
```

## Requirements

PHP 8.5 or later with mbstring and XMLWriter, Symfony 8.2 or later, Twig 3.21 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
