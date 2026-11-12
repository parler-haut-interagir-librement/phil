# phil/structured-data-bundle

**One schema.org graph per page of a Symfony site, gathered from the configuration, other packages and the
templates, and printed as one JSON-LD script element.**

phil/structured-data-bundle brings phil/structured-data into a Symfony application. Each request has its graph. The
configuration describes the site as a `WebSite`, its owner as an `Organization` or one of its kinds, and each page as
a `WebPage` part of the site. Providers of other packages and of the application add their nodes, autoconfigured,
and so do the listeners of the `CollectStructuredData` event. Controllers and templates add the nodes of the page
itself: an event, a product, a book. The breadcrumb of a phil/menu-bundle menu becomes the page's `BreadcrumbList`.
Nodes with one `@id` merge, so a provider that adds the telephone of the organisation completes the one of the
configuration. The Twig function `structured_data()` prints everything as one element, a data block that a content
security policy does not execute, so a page keeps its shared cache; a template can pass it a nonce, or the site can
take the script nonce of phil/browser-security-bundle. A page that shows a record whose owner withdrew it from search
engines, as phil/indexing-consent names it, prints no JSON-LD. With phil/social-cards-bundle, the page is shared with
the title, description and image of its own nodes. `phil:structured-data:check` reads the JSON-LD of a page, at its
address or in a saved file, and reports what it lacks.

Use it in a Symfony site whose pages should be read by search engines, where the site's identity, the breadcrumb and
the content come from different packages. phil/contact-bundle and phil/calendar-bundle pour their cards and events
into the graph when the bundle is installed, instead of printing their own elements.

It plugs into the request stack, the event dispatcher, Twig and the console. The graph of a page is held by its
request and reset by the kernel between two requests of a worker.

## Status

| Part | State |
|---|---|
| The graph of each request, its providers and the `CollectStructuredData` event | Implemented and tested |
| The site, its owner and the page from the configuration | Implemented and tested |
| The breadcrumb of a phil/menu-bundle menu | Implemented and tested |
| `structured_data()`, `structured_data_add()`, `structured_data_about()` | Implemented and tested |
| The nonce given, of a request attribute or of phil/browser-security-bundle's policy | Implemented and tested |
| No JSON-LD for a record withdrawn from search engines (phil/indexing-consent) | Implemented and tested |
| The title, description and image of phil/social-cards-bundle read from the graph | Implemented and tested |
| Reset between requests, in a worker | Implemented and tested |
| `phil:structured-data:check` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | The site, its owner, the page, the language, the breadcrumb, the nonce. |
| [The graph of a page](doc/graph.md) | Where the nodes come from, in which order, how a worker keeps pages apart, and a record withdrawn from search engines. |
| [Twig](doc/twig.md) | `structured_data()`, `structured_data_add()` and `structured_data_about()`. |
| [Checking a page](doc/check.md) | `phil:structured-data:check`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
# config/packages/phil_structured_data.yaml
phil_structured_data:
    website:
        name: Example Press
        search_url: 'https://example.com/recherche?q={search_term_string}'
    organization:
        name: Example Press
        logo: 'https://example.com/logo.png'
    breadcrumb:
        menu: main
```

```twig
{# templates/base.html.twig, at the end of the body #}
{{ structured_data() }}
```

```php
use Phil\StructuredData\Contract\NodeCollector;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\StructuredData\Schema;

public function show(Tournament $tournament, NodeCollector $graph, Request $request): Response
{
    $graph->add(Schema::event($tournament->name(), $tournament->start(), $tournament->hall()));
    // A tournament its organiser withdrew from search engines prints no JSON-LD.
    $request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, $tournament->indexing());
    // …
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, `phil/structured-data`, `phil/indexing-consent` and `phil/url`. Twig for the functions,
`symfony/http-client` to check a page at its address, `phil/menu-bundle` for the breadcrumb,
`phil/browser-security-bundle` for the nonce of its content security policy.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Example Press; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
