# Configuration

The bundle's configuration lives under `phil_feed`:

```yaml
# config/packages/phil_feed.yaml
phil_feed:
    limit: 20
    formats: [atom, rss, json]
    cache:
        max_age: 900
        shared_max_age: ~
    websub:
        hubs: ['https://hub.example.net/']
        timeout: 5.0
        max_duration: 10.0
        user_agent: 'ExampleSite/1.0 (+https://example.org/)'
    content:
        news-fr:
            kind: news
            locale: fr
            title: 'Nouvelles'
            home_page: 'https://example.org/fr/nouvelles'
            description: 'Les nouvelles du site.'
            tag_authority: 'example.org,2026'
```

| Option | Default | What it does |
|---|---|---|
| `limit` | `20` | The entries of a page of a feed, 1 to 500. |
| `formats` | `[atom, rss, json]` | The formats served; a format left out is a `404` and is not linked. At least one. |
| `allow_localhost` | `false` | Whether the feeds, the pages of the content feeds and the hubs may be on `localhost` or under `.localhost`: `true` in development and tests only. |
| `cache.max_age` | `900` | The seconds a feed may be kept by browsers and shared caches; `0` keeps it in none. |
| `cache.shared_max_age` | `null` | The seconds a feed may be kept by shared caches — a reverse proxy, a CDN —; `null` follows `max_age`. |
| `websub.hubs` | `[]` | The absolute addresses of the WebSub hubs told when a feed changes. None turns the announcements off. |
| `websub.timeout` | `5.0` | The seconds a hub may stay silent, 0.1 to 60. |
| `websub.max_duration` | `10.0` | The seconds an announcement to a hub may last, 0.1 to 120. |
| `websub.user_agent` | `PhilFeed/0.1` | The `User-Agent` of the announcements. |
| `content` | `{}` | Feeds of `phil/content-bundle`, by name: see [Sources](sources.md#content-feeds). |

## A content feed

| Key | Required | What it is |
|---|---|---|
| The name | yes | 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit: the `{name}` of the address |
| `kind` | yes | The resource key of the kind of content, as `news` |
| `locale` | yes | The locale whose contents the feed lists, as `fr` or `fr_CA`; the feed's language is its BCP 47 tag, `fr-CA` |
| `title` | yes | The title of the feed |
| `home_page` | yes | The absolute address of the page of the site the feed follows |
| `description` | no | A sentence on the feed |
| `tag_authority` | no | A domain and a date, as `example.org,2026`: each entry is then named `tag:example.org,2026:news:{id}`, which survives a change of slug. Without it, an entry is named by its address |

## What the configuration refuses

A hub or a home page that `phil/url` does not read as an absolute public address — or on `localhost` without
`allow_localhost` —, a feed name, a kind, a locale or a tag authority of the wrong form, a limit outside 1 to 500, a
negative cache duration, and an unknown format are refused when the container is built, with the path of the option.
