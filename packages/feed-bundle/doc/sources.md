# Sources

A feed is a service that implements `Phil\FeedBundle\Application\Contract\FeedSource`. Autoconfiguration tags it
`phil_feed.source`; it is then served, linked and announced.

```php
interface FeedSource
{
    public function name(): string;
    public function channel(): Channel;
    public function items(FeedQuery $query): FeedPage;
    public function includes(PageEvent $page): bool;
}
```

## The name

1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit, unique among the
sources: `news`, `events_2026`, `news-fr`. It is the `{name}` of `/feeds/{name}.{format}`. A name of another form, or
taken by another source, stops the first request that reads the feeds with `InvalidSource`.

## The channel

`channel()` gives what the feed says of itself, **without reading its entries**: every page that calls
`phil_feed_links()` asks for it.

| Field | Rule |
|---|---|
| `title` | 1 to 255 characters on one line, trimmed |
| `homePage` | A `Url`: the page of the site the feed follows |
| `description` | At most 4096 bytes; `null` when empty |
| `language` | A BCP 47 tag, `fr` or `fr-CA`, at most 35 characters |
| `authors` | At most 32 `Author` |
| `icon` | A `Url` |

## The entries

`items()` gives at most `$query->limit` entries, the most recent first, for the page `$query->page`;
`$query->offset()` is `(page - 1) × limit`. It answers a `FeedPage`: the entries, and whether an older page follows.
A source that reads one entry more than the limit can give it instead of saying so: the bundle keeps the limit and
reads the extra entry as an older page. A source that keeps no older pages answers page 1 and an empty list after it;
a page past the last is a `404`.

A `FeedItem` is checked when it is built, so that no format writes what another would refuse:

| Field | Rule |
|---|---|
| `id` | An absolute IRI of at most 2048 bytes: `tag:example.org,2026:news-42`, `urn:uuid:…`, or the address when the entry never moves. It names the entry for good: a reader that sees it again knows it already has it |
| `title` | 1 to 1024 characters on one line, trimmed |
| `url` | A `Url`: the page readers open |
| `published` | When it first went online |
| `updated` | When it last changed; `null`, or a date before `published`, is read as `published` |
| `summary` | Plain text, at most 64 KiB; `null` when empty |
| `contentHtml` | The whole entry as HTML, at most 1 MiB; the writers escape it as each format asks. The bundle does not purify it: it is the HTML the site shows on the entry's page |
| `authors` | At most 32 `Author`: a name, and perhaps a page and a picture |
| `categories` | At most 64 `Category`: a term, and perhaps a label |
| `attachment` | An `Attachment`: a `Url`, a media type without parameters, and perhaps a size in bytes, a title and a duration in seconds — the episode of a podcast |
| `language` | The entry's own BCP 47 tag, when it is not the feed's |

Every text is UTF-8: a text that is not, or breaks its rule, throws `InvalidFeed`, whose message quotes at most 40
characters of the value. A feed holds at most 500 entries.

## The pages of `phil/publishing`

`includes()` says whether a page that went online or offline is one the feed lists, or listed: its hubs are then told
the feed changed. It reads the event's fields — `ownerId`, `locale`, `canonicalUrl`, `indexable` — and never asks a
database for more when it can help it, since it runs for every feed on each publication. A source that cannot tell
answers `false`; its feed is never announced.

## Content feeds

`phil_feed.content` turns the published contents of a kind of `phil/content-bundle` into a feed, with no class to
write:

```yaml
phil_feed:
    content:
        news-fr: { kind: news, locale: fr, title: 'Nouvelles', home_page: 'https://example.org/fr/nouvelles', tag_authority: 'example.org,2026' }
```

The feed lists the contents of the kind that the locale publishes itself, with a slug, the most recently published
first, less those whose SEO says `noindex`. Each entry has the title and the summary the content's page shows — the
excerpt's, else the SEO's —, its HTML body, its absolute address and when it was last published; a content without an
address, or never published, is left out. Its identifier is `tag:{tag_authority}:{reference}` with a tag authority,
else its address. A page belongs to the feed when its owner identifier is a reference of the kind and its language
is the locale's. A page left out as `noindex` still counts on its page: a page of the feed may then hold fewer
entries than the limit.
