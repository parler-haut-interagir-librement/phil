# Extension points

What an application implements, configures or relies on.

## Sources

A service implementing `Phil\FeedBundle\Application\Contract\FeedSource` is tagged `phil_feed.source` by
autoconfiguration; a bundle that registers its sources by hand adds the tag. Each is a feed: see [Sources](sources.md).
A source is a service shared by every request: it keeps nothing of one request for the next.

## Writers

A service implementing `Phil\FeedBundle\Application\Contract\FeedWriter`, tagged `phil_feed.writer`, writes a format.
An application that writes a format its own way — to add the elements of an extension, as the iTunes elements of a
podcast — decorates the bundle's writer of that format (`decorates: Phil\FeedBundle\Application\Writer\AtomWriter`),
and may hand it the inner writer. The formats are those of `FeedFormat`; a new format is a change of the bundle.

## Addresses

`Phil\FeedBundle\Application\Contract\FeedUrls` writes the address of a feed. The bundle's, `RouterFeedUrls`, uses the
route `phil_feed`; an application that serves its feeds from another host replaces the alias.

## Configuration

| Option | What it extends |
|---|---|
| `limit` | The entries of a page |
| `formats` | The formats served and linked |
| `cache` | How long a feed may be cached |
| `websub` | The hubs, their timeouts and the `User-Agent` |
| `content` | The content feeds |

## Events and messages

| Type | Role |
|---|---|
| `Phil\Publishing\PagePublished`, `PageRetired` | Listened to when a hub is configured |
| `Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate` | Routed by the application to an asynchronous transport |

## Stability

The bundle is in alpha: `FeedSource`, `FeedWriter`, `FeedUrls`, the value objects, the route, the Twig function, the
configuration and the message may change in a minor version, as its CHANGELOG says. The classes marked `@internal` —
`Text`, `XmlText`, `ContentEntries`, `PublishedContentEntries` — are not extension points.
