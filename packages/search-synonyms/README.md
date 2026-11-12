# phil/search-synonyms

**Search synonyms per language: equivalent and one-way rules, validated, read and written in the Solr synonyms format
or JSON, kept in files or in the application's own store.**

phil/search-synonyms is a plain PHP library. It holds the synonyms a site's search reads in each language: equivalent
phrases, any of which finds the others — `jdr, jeu de rôle` —, and one-way rules, whose left side is searched as
their right side — `donjon => donjon, dungeon`. Each phrase is kept as a search compares words, in lower case with one
space between words, and every rule is checked as it is built: a phrase too long, a rule with an empty side, a rule
that changes nothing are refused.

The rules of a language are read from, and written to, the Solr synonyms format — the text file many engines load —
or a JSON document fit for an export or an administration form; a file that is no list of rules is refused with the
number of its line. A validator notices what is allowed but likely a mistake before an administrator saves a list: a
phrase on the left of two rules, or a one-way rule leading to a phrase another rule rewrites.

The lists are kept behind the `SynonymStore` port: a directory of files, one per language, written atomically; memory;
or a table of the application's. `phil/search-bundle` reads a store through its synonyms port, so that a search for
`jdr` also finds `jeu de rôle`.

## Status

| Part | State |
|---|---|
| `SynonymSet`, `SynonymList`, `InvalidSynonyms` | Implemented and tested |
| `SolrSynonymsReader`, `SolrSynonymsWriter`, `JsonSynonyms` | Implemented, tested and fuzzed |
| `SynonymValidator`, `Violation` | Implemented and tested |
| `SynonymStore`, `FileSynonymStore`, `InMemorySynonymStore` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Rules](doc/rules.md) | Equivalent and one-way rules, phrases, limits, the validator. |
| [Formats](doc/formats.md) | The Solr synonyms format and the JSON document, read and written. |
| [Stores](doc/stores.md) | Files, memory, a store of the application's; with `phil/search-bundle`. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and fuzz targets. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsReader;
use Phil\SearchSynonyms\Infrastructure\Store\FileSynonymStore;

$list = new SolrSynonymsReader()->read("jdr, jeu de rôle\ndonjon => donjon, dungeon\n", 'fr');
$store = new FileSynonymStore(__DIR__.'/config/synonyms');
$store->save($list);            // config/synonyms/fr.txt
$store->get('fr-CA')->sets;     // the two rules
```

## Requirements

PHP 8.5 or later with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
