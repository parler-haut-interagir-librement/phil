# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\FeedBundle\PhilFeedBundle` | `AbstractBundle` | The `phil_feed` configuration and the services |
| `…\Application\Contract\FeedSource` | interface | A feed: `name()`, `channel()`, `items()`, `includes()` |
| `…\Application\Contract\FeedWriter` | interface | Writes a feed in one format: `format()`, `write($feed, $links)` |
| `…\Application\Contract\FeedUrls` | interface | The absolute address of a feed in a format and a page |
| `…\Application\Feeds` | `final class` | The sources by name: `names()`, `source()`, `feed($name, $page)`, `including($page)` |
| `…\Application\FeedWriters` | `final readonly class` | The writers of the formats served: `for($format)`, `formats()` |
| `…\Application\FeedLinkBuilder` | `final readonly class` | The self, hub, first, previous and next links of a written feed |
| `…\Application\Writer\AtomWriter` | `FeedWriter` | Atom, RFC 4287 |
| `…\Application\Writer\RssWriter` | `FeedWriter` | RSS 2.0 |
| `…\Application\Writer\JsonFeedWriter` | `FeedWriter` | JSON Feed 1.1 |
| `…\Application\Exception\UnknownFeed` | `RuntimeException` | No source has that name |
| `…\Application\Exception\InvalidSource` | `LogicException` | A source named as no address holds, or two sources of one name |
| `…\Domain\FeedFormat` | enum | `Atom`, `Rss`, `Json`: `mediaType()`, `contentType()`, `label()` |
| `…\Domain\ValueObject\Channel` | value object | What a feed says of itself |
| `…\Domain\ValueObject\FeedItem` | value object | One entry |
| `…\Domain\ValueObject\Author` | value object | A name, a page, a picture |
| `…\Domain\ValueObject\Category` | value object | A term and a label; `display()` |
| `…\Domain\ValueObject\Attachment` | value object | A file of an entry: address, media type, size, title, duration |
| `…\Domain\ValueObject\FeedQuery` | value object | The limit and the page asked of a source; `offset()` |
| `…\Domain\ValueObject\FeedPage` | value object | The entries a source gives, and whether an older page follows |
| `…\Domain\ValueObject\Feed` | value object | A channel, one page of entries, `updated`, `isEmpty()`, `fingerprint()` |
| `…\Domain\ValueObject\FeedLinks` | value object | The addresses a written feed names; `feedId()` |
| `…\Domain\Exception\InvalidFeed` | `InvalidArgumentException` | A part no format could write |
| `…\Infrastructure\Http\FeedController` | controller | `/feeds/{name}.{format}` with its cache headers |
| `…\Infrastructure\Routing\RouterFeedUrls` | `FeedUrls` | The addresses through the route `phil_feed` |
| `…\Infrastructure\Twig\FeedExtension`, `FeedRuntime` | Twig | `phil_feed_links(names, formats)` |
| `…\Infrastructure\WebSub\AnnounceChangedFeeds` | listener | `PagePublished` and `PageRetired` to `AnnounceFeedUpdate` |
| `…\Infrastructure\WebSub\AnnounceFeedUpdate` | message | The name of a feed |
| `…\Infrastructure\WebSub\AnnounceFeedUpdateHandler` | handler | Tells the hubs |
| `…\Infrastructure\WebSub\Exception\HubNotificationFailed` | `RuntimeException` | A hub did not take the announcement; Messenger retries |
| `…\Infrastructure\Content\ContentFeedSource` | `FeedSource` | A feed of a kind of `phil/content-bundle` in a locale |
| `…\Infrastructure\DependencyInjection\FeedServices` | `final class` | Registers the services and the tags |

## Services

| Id | When |
|---|---|
| `Phil\FeedBundle\Application\Feeds` | Always; public |
| `Phil\FeedBundle\Application\FeedWriters`, `FeedLinkBuilder` | Always |
| `Phil\FeedBundle\Application\Writer\AtomWriter`, `RssWriter`, `JsonFeedWriter` | For each format of `formats`, tagged `phil_feed.writer` |
| `Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls`, aliased `FeedUrls` | Always |
| `Phil\FeedBundle\Infrastructure\Http\FeedController` | Always; public |
| `Phil\FeedBundle\Infrastructure\Twig\FeedExtension`, `FeedRuntime` | Always, tagged `twig.extension` and `twig.runtime` |
| `phil_feed.websub_client` | With a hub: a `SafeHttpClient`, reset between messages |
| `Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds`, `AnnounceFeedUpdateHandler` | With a hub |
| `phil_feed.content.{name}` | For each content feed |

## Constants

| Constant | Value |
|---|---|
| `FeedServices::SOURCE_TAG` | `phil_feed.source` |
| `FeedServices::WRITER_TAG` | `phil_feed.writer` |
| `FeedServices::WEBSUB_CLIENT` | `phil_feed.websub_client` |
| `RouterFeedUrls::ROUTE` | `phil_feed` |
| `Feeds::NAME_PATTERN` | `[a-z0-9][a-z0-9_-]{0,63}` |
| `FeedQuery::MAX_LIMIT`, `Feed::MAX_ITEMS` | `500` |
| `FeedQuery::MAX_PAGE` | `1000` |

## The formats as written

| | Atom | RSS 2.0 | JSON Feed 1.1 |
|---|---|---|---|
| Media type | `application/atom+xml` | `application/rss+xml` | `application/feed+json` |
| Dates | RFC 3339, UTC | RFC 822, UTC | RFC 3339, UTC |
| Feed identifier | First page's address | — | — |
| Feed's authors | `author`, else its title when it has no entry or an entry has none | — | `authors` |
| Entry title | `title type="text"` | `title` | `title` |
| Summary | `summary type="text"` | `description`, escaped into HTML | `summary` |
| Content | `content type="html"` | `content:encoded`, and `description` without a summary | `content_html`, else `content_text` from the summary or the title |
| Identifier | `id` | `guid`, `isPermaLink="true"` when it is the address | `id` |
| Authors | `author` with `name`, `uri` | `dc:creator` | `authors` with `name`, `url`, `avatar` |
| Categories | `category term label` | `category` of the label, else the term | `tags` |
| Attachment | `link rel="enclosure"` | `enclosure`, length 0 when unknown | `attachments` |
| Pages | `link rel="first"`, `previous`, `next` | `atom:link` of the same | `next_url` |

The XML writers drop the characters XML 1.0 cannot hold — a control character in the HTML of an entry —, which no
parser would read; `XMLWriter` escapes the rest.
