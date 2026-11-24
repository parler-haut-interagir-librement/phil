# WebSub

A WebSub hub pushes a feed to its subscribers as soon as it changes, instead of their asking for it again and again.
The bundle is the publisher: each feed names its hubs, and the hubs are told when a feed changes. It hosts no hub;
the application names a public one, or one it runs.

## Turning it on

```yaml
phil_feed:
    websub:
        hubs: ['https://hub.example.net/']

framework:
    messenger:
        routing:
            Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate: async
```

Without a hub, nothing is registered: no listener, no handler, no HTTP client.

## What a feed names

| Format | Its own address | Its hubs |
|---|---|---|
| Atom | `<link rel="self" href="…"/>` | `<link rel="hub" href="…"/>` |
| RSS | `<atom:link rel="self" href="…"/>` | `<atom:link rel="hub" href="…"/>` |
| JSON Feed | `feed_url` | `hubs: [{"type": "WebSub", "url": "…"}]` |

The self address is the route of the feed, page 1, absolute: the topic a subscriber subscribes to.

## The announcement

1. A package dispatches `PagePublished` or `PageRetired` of `phil/publishing` once the change is stored.
2. `AnnounceChangedFeeds` asks each source whether it `includes()` the page, and dispatches an
   `AnnounceFeedUpdate` per feed that does. The message holds the name of the feed and nothing else: neither an
   address, nor a title, nor a content.
3. `AnnounceFeedUpdateHandler`, in the worker, writes the address of the feed in each format served and tells each
   hub: a `POST` of `hub.mode=publish&hub.url={topic}`, `application/x-www-form-urlencoded`, through `phil/safe-http`.
   The hub then reads the feed and pushes it to its subscribers.

In a worker, the router writes the topic from its request context: `framework.router.default_uri` gives it the site's
address.

## What is refused, and what is retried

| Case | What happens |
|---|---|
| A hub that is, or resolves to, a loopback, private, link-local or reserved address | Refused by `phil/safe-http` before any connection, logged as a warning, not retried |
| A hub that does not answer within `timeout` or `max_duration`, or answers other than `2xx` | Logged as a warning; once every hub was tried, `HubNotificationFailed` hands the message back to Messenger, which retries it by its retry strategy |
| A redirect from a hub | Not followed: a `POST` follows no redirect, and a `3xx` is not a `2xx` |
| A feed that no longer exists | Logged as information, skipped |

A retry tells again the hubs that took the announcement: WebSub allows it, and a hub reads the feed once more. The
answer of a hub is read up to 1 KiB; its status is what counts. The log messages are constants with the hub, the
topic and the reason in their context.

A burst of publications sends one message per page and feed. An application that publishes many pages at once may
add Messenger's `DeduplicateStamp` in a middleware, or delay the messages, so that a hub is told once.
