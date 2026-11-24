# Configuration

```yaml
# config/packages/phil_newsletter_archive.yaml
phil_newsletter_archive:
    enabled: true
    connection: doctrine.dbal.default_connection
    page_size: 20
    slugger: ~
    slug_max_length: 120
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the archive's services; `false` registers none. |
| `connection` | `doctrine.dbal.default_connection` | The Doctrine DBAL connection of the archive's tables. |
| `page_size` | `20` | How many issues a page of the archive lists, 1 to 100. |
| `slugger` | `null` | The service of a `Phil\Slug\Slugger` that makes the slugs of the issues, from `phil/slug-bundle` for one. By default, a `TransliteratingSlugger`. |
| `slug_max_length` | `120` | The longest slug the default slugger makes, 16 to 200, cut between two words. |

The site of a request is the one `phil/newsletter-bundle` resolves, through its `CurrentSite` service: an application
that serves several sites keeps their archives apart with it.
