<!-- phil-dev source-sha256=55b32ca126a38d5263ed3729f030bdd9b4711787ac44d87b0b4ab9183aebb6fc -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le paquet |
| `pdo_sqlite`, `mbstring` | SQLite 3.35 ou plus récent | Ce que Loupe requiert |
| `loupe/loupe` | ^1.1 | Les index |
| `phil/search-bundle` | ^0.1 ou ^0.2 | Le port `SearchIndex`, et le bundle qui enregistre l'adaptateur |

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
