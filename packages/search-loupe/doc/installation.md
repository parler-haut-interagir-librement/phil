# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the package declares |
| `pdo_sqlite`, `mbstring` | SQLite 3.35 or later | What Loupe requires |
| `loupe/loupe` | ^1.1 | The indexes |
| `phil/search-bundle` | ^0.1 | The `SearchIndex` port, and the bundle that registers the adapter |

```sh
composer require phil/search-loupe
```

`loupe/loupe` requires `wamania/php-stemmer`, which requires `joomla/string`, under the GNU GPL 2.0 or later. An
application that installs this package installs it; this package's own code, and Loupe's, are MIT.

## Choosing the adapter

```yaml
# config/packages/phil_search.yaml
phil_search:
    adapter: loupe
    loupe:
        directory: '%kernel.project_dir%/var/loupe'   # a directory per index
```

`phil/search-bundle` registers `LoupeIndex` as its `SearchIndex` when this package is installed; `adapter: loupe`
without it is refused when the container is built. The directory must be writable by the application and kept on a
persistent volume; deleting it empties the indexes, which `phil:search:reindex` fills again.

Without the bundle, the adapter is a class like any other:

```php
use Loupe\Loupe\LoupeFactory;
use Phil\SearchLoupe\LoupeIndex;

$index = new LoupeIndex(new LoupeFactory(), '/srv/search');   // or no directory: in memory
```
