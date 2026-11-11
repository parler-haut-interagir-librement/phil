# phil/slug-bundle

**Named sluggers declared in the configuration, autowired by name and usable in Twig.**

phil/slug-bundle brings phil/slug into a Symfony application. The configuration names the sluggers an application
needs, one per use, each with its locale, its longest slug and the leading words it drops. Each named slugger is a
service of its own, so the application decorates it like any Symfony service, and the decorator takes its place
everywhere. The default slugger autowires as `Slugger`, every named one as `Slugger $<name>Slugger`, and `Sluggers`
holds them all by name. With Twig installed, the `phil_slug` filter makes a slug in a template. With the history
enabled, the former slugs of each subject point to its current slug, so an old address can answer with a redirect.

Use it when a Symfony application makes slugs in more than one way, or wants the same slugger in its services and its
templates: titles cut at 80 characters, directory names without their leading article. Outside Symfony, phil/slug
alone builds the same sluggers by hand. No slugger drops words unless the configuration says so, since the articles
of a language and a site's own prefixes are each application's choice.

The bundle holds no slug logic of its own. It turns each configured slugger into a `TransliteratingSlugger`, wrapped
in a `LeadingWordsRemoved` when it lists leading words, and registers the aliases that make autowiring by argument
name work.

## Status

| Part | State |
|---|---|
| Named sluggers from the configuration, `Sluggers`, autowiring by name | Implemented and tested |
| Decoration of a named slugger | Implemented and tested |
| The `phil_slug` Twig filter | Implemented and tested |
| The history of former slugs in a Doctrine DBAL table, optional | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | The sluggers, their options, the default one. |
| [Services](doc/services.md) | The service ids, autowiring by name, decorating a slugger. |
| [Twig](doc/twig.md) | The `phil_slug` filter. |
| [History of former slugs](doc/history.md) | The optional table of former slugs, recording a change, reading it for redirects. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    sluggers:
        default: ~
        directory:
            leading_words: [le, la, les, l, the]
```

```php
public function __construct(private Slugger $directorySlugger) {}

$this->directorySlugger->slug('Les Gardiens')->value;   // 'gardiens'
```

```twig
<li data-sort-name="{{ name|phil_slug('directory') }}">{{ name }}</li>
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, `phil/slug`; Twig for the filter; `doctrine/dbal` for the history.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
