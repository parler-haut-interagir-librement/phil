# Changelog

All notable changes to `phil/feed-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `FeedSource`, the port of a feed: a name, a channel read without the entries, the entries of a page — at most the
  limit, the most recent first, an extra one meaning an older page — and the pages of `phil/publishing` it lists.
- The value objects of a feed — `Channel`, `FeedItem`, `Author`, `Category`, `Attachment`, `FeedQuery`, `FeedPage`,
  `Feed`, `FeedLinks` — checked when built: UTF-8, bounded texts, an absolute IRI as identifier, BCP 47 languages, at
  most 500 entries; `InvalidFeed` otherwise.
- Atom (RFC 4287), RSS 2.0 and JSON Feed 1.1 writers from the same feed, the XML by `XMLWriter`: escaped HTML content,
  dates in UTC — RFC 3339, RFC 822 for RSS —, attachments, categories, authors, the self and hub links, the pages of
  RFC 5005, and the characters XML cannot hold dropped.
- `/feeds/{name}.{format}` and `?page=`: the media type of each format, `X-Content-Type-Options: nosniff`, an `ETag`
  from the fingerprint of the feed, the latest change as `Last-Modified`, a `304` before the feed is written, cache
  headers set by `phil_feed.cache`, and a `404` for an unknown feed, format or page.
- `phil_feed_links()`, the `<link rel="alternate">` of the feeds of a page, by name and format.
- WebSub announcements: on `PagePublished` and `PageRetired`, an `AnnounceFeedUpdate` naming the feed alone; its
  handler tells each configured hub through `phil/safe-http`, refuses a private hub for good and hands the other
  failures back to Messenger. Off without a hub.
- Content feeds of `phil/content-bundle` under `phil_feed.content`: the published, indexable contents of a kind in a
  locale, named by their address or by a `tag:` URI.
- The configuration `phil_feed`: `limit`, `formats`, `allow_localhost`, `cache`, `websub`, `content`.
- A Flex recipe in `recipe/0.1`, a fuzz target of the writers, and the documentation in `doc/`, one file per topic,
  indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
