<!-- phil-dev source-sha256=c6ddb521889ef895d0c17ec91bca3c449c9876fe0c364e490071bddbb8407392 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Les ports

Le bundle associe chaque port de `phil/stockroom` à son adaptateur par un alias. Une application qui garde une partie de
la réserve ailleurs fait pointer le port vers son propre service :

| Port | Adaptateur du bundle (`dbal`) | En mémoire (`memory`) |
|---|---|---|
| `Phil\Stockroom\Contract\Containers` | `DbalContainers` | `InMemoryContainers` |
| `Phil\Stockroom\Contract\Items` | `DbalItems` | `InMemoryItems` |
| `Phil\Stockroom\Contract\Balances` | `DbalBalances` | `InMemoryBalances` |
| `Phil\Stockroom\Contract\Journal` | `DbalJournal` | `InMemoryJournal` |
| `Phil\Stockroom\Contract\LoadLists` | `DbalLoadLists` | `InMemoryLoadLists` |

```yaml
# config/services.yaml
services:
    Phil\Stockroom\Contract\Journal: '@App\Stock\ArchivedJournal'
```

Un adaptateur de l'application respecte le contrat que donne la page `extension-points` de la bibliothèque : les listes
dans l'ordre des identifiants ou des clés, le journal du plus récent au plus ancien, écrit une seule fois.

## Les services

| Service | Ce que c'est |
|---|---|
| `Phil\Stockroom\Stockroom` | La réserve sur les stockages configurés, datée par l'horloge configurée ; public. |
| `Phil\Stockroom\Contract\StockReader` | Un alias de `Stockroom`, pour un paquet qui ne fait que lire le stock. |
| `Phil\Stockroom\Deployments` | Les listes de chargement et leurs déploiements ; public. |
| `Phil\Stockroom\LoadListReader` | Le lecteur des listes de chargement écrites en texte ; public. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | Les transactions qui verrouillent les articles qu'elles déplacent ; public. |

## La connexion

Le paramètre `phil_stockroom.connection` contient l'identifiant du service de la connexion des tables, ou `null` avec le
stockage `memory`. Un autre bundle le lit pour savoir si ses écritures partagent la connexion de la réserve, et donc ses
transactions, comme le fait `phil/library-bundle`.

## Décorer

Chaque service est un service Symfony ordinaire : décorez les stockages de `Stockroom` pour ajouter un cache ou un
audit, ou `StockroomTransactions` pour ajouter une métrique, avec `#[AsDecorator]` ou `decorates:`.
