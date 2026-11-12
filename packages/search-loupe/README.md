# phil/search-loupe

**Typo-tolerant search in SQLite files for phil/search-bundle, with no server to run.**

phil/search-loupe is the Loupe adapter of `phil/search-bundle`. It keeps each of the bundle's indexes in SQLite files,
in a directory of its own, and brings Loupe's relevance and typo tolerance. Only the text fields are searched, and a
document is found when it holds every word looked for, as with the bundle's `dbal` adapter. It adds nothing to the
bundle's API: providers, queries and results stay the same.

Reach for it on a site that wants better ranking than a database table gives, without running and securing a
Meilisearch server: shared hosting, a small server, a test suite. A site with a large index or several servers writing
to it fits Meilisearch better, and a site with modest needs fits the bundle's default `dbal` adapter.

It is a package of its own so that `phil/search-bundle` declares no dependency on Loupe, whose own dependencies
include a package under a reciprocal licence ([Licences](#licences)). The single class, `LoupeIndex`, implements the
bundle's `SearchIndex` port; the bundle picks it up with `adapter: loupe`. Loupe declares an index's fields before it
indexes, so the adapter learns them as documents come, in the order a provider gives them, keeps them in
`phil_search.json` beside the index, and fills the index again once when a new field appears. Every process checks
that file before using the index, and a rebuild or a new field fills a new directory before switching to it, so the
index stays searchable.

## Status

| Part | State |
|---|---|
| `LoupeIndex` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the licences, choosing the adapter, the directory of the indexes. |
| [How it indexes](doc/indexing.md) | The fields searched and filtered, the rebuild when a field appears, what matches. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the package is tested. |

## Quick start

```sh
composer require phil/search-loupe
```

```yaml
# config/packages/phil_search.yaml
phil_search:
    adapter: loupe
    loupe:
        directory: '%kernel.project_dir%/var/loupe'
```

## Requirements

PHP 8.5 or later with `pdo_sqlite` on SQLite 3.35 or later and `mbstring`; Loupe 1.1; `phil/search-bundle` 0.1.

## Licences

This package is MIT. Loupe is MIT too, but it requires `wamania/php-stemmer`, which requires `joomla/string`, under
the GNU GPL 2.0 or later: an application that installs this package installs that one. Read its licence before
choosing this adapter; `phil/search-bundle`'s other adapters do not bring it.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
