# phil/social-cards-bundle

**The share tags and card of every page of a Symfony site, safe previews of the links to other sites, and a check of
a page's tags.**

phil/social-cards-bundle brings phil/social-cards into a Symfony application. `{{ social_meta() }}` in the head of a
layout writes the canonical link, the description, the Open Graph, X and fediverse tags of the page, from four layers:
the defaults of the configuration, the sources of the application — a content page, an article, a product —, what the
controller or the template sets, and what the call passes. A page without an image gets its card, drawn from a
template of the configuration and served from `/social-cards/{fingerprint}.png` with a cache of a year; with
Messenger, a worker draws it, the message carrying the fingerprint alone.

`<twig:PhilSocialCards:LinkPreview url="…" />` shows the card of a link to another site, read from that page's tags
through phil/safe-http — a client that refuses private networks, checks each redirect, bounds the time, the size and
the redirects — and cached. The preview of a link a member pastes in a form comes from an endpoint behind a rate limit
and a Stimulus controller that lets the author remove it; the application keeps it with the message, and the component
shows that snapshot without fetching again.
`phil:social-cards:check` renders a page of the application, or fetches one of another site, and says what its tags
lack.

Use it in any Symfony site whose pages are shared: each page gets complete tags and an image without anyone drawing
one. phil/media-bundle gives a page a medium of its library as its image; phil/http-cache-tags-bundle tags the served
cards for a purge.

## Status

| Part | State |
|---|---|
| `social_meta()`, `social_page()`, `SharePage`, the sources | Implemented and tested |
| The cards, their route, their store and their worker | Implemented and tested |
| The guarded fetcher, `LinkPreviews` and the `LinkPreview` component | Implemented and tested |
| Pasted links: `LinkPreviews::firstIn()`, the endpoint and the `link-preview` controller | Implemented and tested |
| `phil:social-cards:check` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the route. |
| [Configuration](doc/configuration.md) | Every option and its default. |
| [Pages](doc/pages.md) | The layers of a page's metadata, `social_meta()`, `social_page()`, the sources. |
| [Cards](doc/cards.md) | Templates, the route, the cache, the worker, the store. |
| [Link previews](doc/previews.md) | The guarded fetcher, the cache, the component and the pasted links. |
| [Commands](doc/commands.md) | `phil:social-cards:check`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures or replaces. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |
| [Testing](doc/testing.md) | How the bundle is tested. |

## Quick start

```yaml
# config/packages/phil_social_cards.yaml
phil_social_cards:
    site_name: 'Example'
    cards:
        templates:
            default: { title_font: '%kernel.project_dir%/assets/fonts/Inter-Bold.ttf', accent: '#e11d48' }

# config/routes/phil_social_cards.yaml
phil_social_cards:
    resource: '@PhilSocialCardsBundle/config/routes.php'
```

```twig
{# a page's template #}
{% do social_page({title: post.title, description: post.summary, type: 'article', published_time: post.publishedAt}) %}

{# the layout's head #}
{{ social_meta() }}
```

## Requirements

PHP 8.5 or later with GD, Symfony 8.2 or later, Twig 3.21 or later, `phil/social-cards` and `phil/safe-http`.
Messenger, the console, the rate limiter, Symfony UX Twig Component and the StimulusBundle are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
