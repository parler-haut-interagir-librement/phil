# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library declares no service. What an application writes is a source of products; what it may add is the
serializer of another format.

| To | Do |
|---|---|
| Publish a catalogue | Implement `Contract\ProductSource` and pass it to `FeedPublisher::publish()` |
| Write another format | Implement `Contract\FeedSerializer` and pass it instead of `FeedFormat::serializer()` |
| Bound the length of a feed | Give `FeedPublisher` its maximum of items, a million by default |

## `ProductSource`

`Phil\ProductFeeds\Contract\ProductSource` — the products a feed publishes.

| Method | Contract |
|---|---|
| `items(): iterable<FeedItem>` | One item per product or variant sold, each identifier once |

A source that reads its catalogue by pages and yields each item keeps the memory of the publisher flat: a generator
over a keyset of pages is the shape to write. A source decides what it publishes — a channel's selection, the
products shown, the variants rather than their family — and builds each item's addresses with `phil/url`. An item it
cannot make, its `InvalidFeedItem` caught, is better left out and logged than allowed to stop the whole feed.

## `FeedSerializer`

`Phil\ProductFeeds\Contract\FeedSerializer` — writes a feed as text, in three parts, so that a feed of any length is
written one item at a time.

| Method | Contract |
|---|---|
| `header(FeedChannel $channel): string` | What comes before the first item; may be empty |
| `item(FeedItem $item): string` | One item, ending where the next one starts |
| `footer(): string` | What comes after the last item; may be empty |

A serializer keeps no state between calls: `FeedPublisher` asks it for the header, each item, then the footer, and
writes each part as it comes. `RssSerializer` and `TsvSerializer` are the package's two.
