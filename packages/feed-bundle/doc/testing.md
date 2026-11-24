# Testing

| Area | What is covered |
|---|---|
| The value objects | Every rule and its bounds, texts not in UTF-8, the update never before the publication, the fingerprint of every field, the formats |
| `AtomWriter`, `RssWriter`, `JsonFeedWriter` | A full feed against a file written once and read — characters outside ASCII, HTML to escape, a podcast episode, pages and a hub —, an empty feed, the elements each format requires, the author of an Atom feed, the description and the content of RSS, the text of JSON Feed, the characters XML cannot hold; JSON Feed checked field by field against its specification |
| `Feeds`, `FeedLinkBuilder`, `FeedWriters` | The names, a name refused or taken twice, the sources indexed once, the pages and the extra entry, the feeds of a page, the links of each page, the writers of the formats served |
| `AnnounceFeedUpdateHandler`, `AnnounceChangedFeeds` | Over a mock HTTP client behind `phil/safe-http`: each hub told of each format, a private hub refused and not retried, a `5xx` and a timeout retried once every hub was tried, a feed gone; the message holding the name alone |
| `ContentFeedSource` | Over contents in memory: the order, `noindex`, no address, never published, a row without an excerpt or a body, the extra content, the tag authority, the pages of the feed |
| `PhilFeedBundle`, `FeedServices` | The defaults, every option, the services and their tags, the content feeds, what the configuration refuses |
| A whole application | FrameworkBundle, TwigBundle, Messenger in memory and two sources: each format and its media type, the pages, the `404`s, an empty feed, `ETag`, `Last-Modified` and the `304`s, the cache headers, `phil_feed_links()`, the announcements, and an application without a hub that serves Atom alone |

```sh
vendor/bin/phpunit packages/feed-bundle/tests
```

## Fuzzing

The target `write` (`tests/Fuzz/write.php`) builds an entry from a title, a summary, an HTML content, a category and
an author nobody wrote, then writes it in the three formats: the entry is refused with `InvalidFeed`, or each document
reads back — XML that DOM parses, with its title, and JSON that `json_decode()` reads. From the monorepo root:

```sh
composer fuzz -- write
```
