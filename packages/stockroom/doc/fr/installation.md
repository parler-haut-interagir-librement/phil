<!-- phil-dev source-sha256=8366c779328c7cfb08ba1ed3fdf90a7bf1dd132f6c1d4b9dd6fb1bd94cefce5c -->
# Installation

```sh
composer require phil/stockroom
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-mbstring`, `psr/clock`, `symfony/uid` pour les
identifiants qu'il fabrique et `symfony/service-contracts` pour la réinitialisation de ses magasins en mémoire.

## Câbler les magasins

`Stockroom` reçoit cinq éléments : les magasins des contenants, des articles, des soldes et du journal, et une horloge.
`Deployments` reçoit la réserve, le magasin des listes de chargement et celui des soldes.

```php
use Phil\Stockroom\Deployments;
use Phil\Stockroom\Stockroom;

$stockroom = new Stockroom($containers, $items, $balances, $journal, $clock);
$deployments = new Deployments($stockroom, $loadLists, $balances);
```

Le paquet fournit les magasins en mémoire, pour les tests, les scripts et les démonstrations. Une application qui
conserve son stock implémente les cinq ports de `Phil\Stockroom\Contract` sur sa base de données — voir
[Points d'extension](extension-points.md) — et exécute chaque appel de la réserve dans une transaction, de sorte qu'un
mouvement et les soldes qu'il change soient écrits ensemble.

Dans une application Symfony, les services se déclarent comme n'importe quel autre :

```yaml
# config/services.yaml
services:
    Phil\Stockroom\Stockroom:
        arguments: ['@App\Stock\Containers', '@App\Stock\Items', '@App\Stock\Balances', '@App\Stock\Journal', '@clock']
    Phil\Stockroom\Deployments:
        arguments: ['@Phil\Stockroom\Stockroom', '@App\Stock\LoadLists', '@App\Stock\Balances']
```

Les services ne gardent aucun état entre deux appels ; les magasins en mémoire implémentent `ResetInterface`, que le
noyau appelle entre les requêtes d'un *worker*.
