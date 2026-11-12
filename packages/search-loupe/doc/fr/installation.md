<!-- phil-dev source-sha256=cc53ce35c8cb0df52e19ed5e67b0759a53cef7d6d6e6de0b65e7080f34e879f0 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le paquet |
| `pdo_sqlite`, `mbstring` | SQLite 3.35 ou plus récent | Ce que Loupe requiert |
| `loupe/loupe` | ^1.1 | Les index |
| `phil/search-bundle` | ^0.1 | Le port `SearchIndex`, et le bundle qui enregistre l'adaptateur |

```sh
composer require phil/search-loupe
```

`loupe/loupe` requiert `wamania/php-stemmer`, qui requiert `joomla/string`, sous GNU GPL 2.0 ou plus récente. Une
application qui installe ce paquet l'installe aussi ; le code propre à ce paquet, et celui de Loupe, sont sous licence
MIT.

## Choisir l'adaptateur

```yaml
# config/packages/phil_search.yaml
phil_search:
    adapter: loupe
    loupe:
        directory: '%kernel.project_dir%/var/loupe'   # a directory per index
```

`phil/search-bundle` enregistre `LoupeIndex` comme son `SearchIndex` quand ce paquet est installé ; `adapter: loupe`
sans lui est refusé à la construction du conteneur. L'application doit pouvoir écrire dans le répertoire, qui doit
rester sur un volume persistant ; le supprimer vide les index, que `phil:search:reindex` remplit de nouveau.

Sans le bundle, l'adaptateur est une classe comme une autre :

```php
use Loupe\Loupe\LoupeFactory;
use Phil\SearchLoupe\LoupeIndex;

$index = new LoupeIndex(new LoupeFactory(), '/srv/search');   // or no directory: in memory
```
