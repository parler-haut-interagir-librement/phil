# Configuration

```yaml
# config/packages/phil_social_cards.yaml — an example, every option shown
phil_social_cards:
    site_name: 'Example'                  # og:site_name, and the subtitle of a card without a description
    locale: ~                             # fr_CA; the request's when null
    twitter_site: '@example'
    fediverse_creator: '@team@example.social'
    default_image: ~                      # an address, or url, alt, width, height, type; used when no card is drawn
    cards:
        enabled: true
        directory: '%kernel.project_dir%/var/social-cards'
        cache_max_age: 31536000
        async: true
        default_template: default
        templates:
            default:
                title_font: '%kernel.project_dir%/assets/fonts/Inter-Bold.ttf'
                subtitle_font: '%kernel.project_dir%/assets/fonts/Inter-Regular.ttf'
                background: '#0f172a'
                title_color: '#f8fafc'
                subtitle_color: '#cbd5e1'
                accent: '#e11d48'
                logo: '%kernel.project_dir%/assets/images/logo.png'
    preview:
        enabled: true
        timeout: 5
        max_duration: 10
        max_bytes: 1048576
        max_redirects: 3
        cache_ttl: 86400
        user_agent: 'PhilSocialCards/0.1 (link preview)'
        limiter: ~                        # limiter.<name> of framework.rate_limiter, for pasted links
```

| Option | Default | Rule |
|---|---|---|
| `site_name`, `locale`, `twitter_site`, `fediverse_creator`, `default_image` | null | Checked as `ShareMetadata` checks them, when the container is built |
| `cards.enabled` | true | Cards are drawn only when at least one template is declared |
| `cards.directory` | `%kernel.project_dir%/var/social-cards` | The cards and their requests; shared by the web servers and the workers |
| `cards.cache_max_age` | 31,536,000 | Seconds, 0 or more |
| `cards.async` | true | The cards are drawn by a worker when `messenger.default_bus` exists |
| `cards.default_template` | `default` | Must be among the templates when any is declared |
| `cards.templates` | none | The keys of `CardTemplate::KEYS`: `title_font` required, colours in hexadecimal, sizes as integers |
| `preview.enabled` | true | The client, the fetcher, the previews, the endpoint, the component and the command |
| `preview.timeout` | 5 | 0.1 to 60 seconds of silence |
| `preview.max_duration` | 10 | 0.1 to 120 seconds for the whole fetch |
| `preview.max_bytes` | 1,048,576 | 1 KiB to 16 MiB read of a page |
| `preview.max_redirects` | 3 | 0 to 10 |
| `preview.cache_ttl` | 86,400 | Seconds a preview stays in `cache.app` |
| `preview.user_agent` | `PhilSocialCards/0.1 (link preview)` | Not empty, one line, no control character; name the site and a way to reach whoever runs it |
| `preview.limiter` | null | The id of a rate limiter factory; the endpoint of pasted links consumes one token per address it reads, by client address |

The fonts and images of a template are read the first time the template is used, where they are installed; the
configuration checks only the shape of a template.
