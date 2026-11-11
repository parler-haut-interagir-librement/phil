# Configuration

```yaml
phil_migration:
    channels:
        discord_releases:                 # the transport, unless `transport` names another
            locale: fr
            title: '# Une nouvelle version est en ligne{counter}'
            continued: '## Suite{counter}'
        english:
            transport: discord_releases_en
            locale: en
            title: '# A new release is online{counter}'
        discord_news:
            locale: fr
            content: news
            heading: '# {title}'
            link: 'Pour voir la nouvelle : {url}'
            max_length: 2000
```

| Option | Default | Role |
|---|---|---|
| `transport` | the name of the channel | The chatter transport, as `framework.notifier.chatter_transports` names it |
| `locale` | `%kernel.default_locale%` | The locale the announcement is read in: a language, and maybe a region |
| `content` | `notes` | `notes`, the release notes, or `news` |
| `title` | `''` | The title of the first message; none when empty |
| `continued` | `''` | The title of the messages that follow; none when empty |
| `heading` | `'{title}'` | The line of a piece of news that holds its title |
| `link` | `'{url}'` | The line of a piece of news that holds its URL; none when empty |
| `max_length` | `2000` | The longest message the transport takes, 100 at least: 2000 characters on Discord |

`{counter}` becomes ` (2/3)` when a release takes more than one message, and nothing when it takes one. A title
takes half of a message at most. A note or a piece of news not written in the locale of a channel is left out of
it, so one channel per locale tells a release in each language.

How a release is cut into messages is described in the reference of `phil/migration`.
