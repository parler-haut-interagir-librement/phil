<!-- phil-dev source-sha256=f782add0a71b58621ad1df8088a9a343488cb9c3f173bd19523a6c3975ce4c87 -->
# Configuration

La configuration du bundle se trouve sous `phil_stockroom`. Chaque clé a une valeur par défaut :

```yaml
# config/packages/phil_stockroom.yaml
phil_stockroom:
    store: dbal
    connection: default
    table_prefix: phil_stockroom_
    clock: clock
```

| Clé | Valeur par défaut | Effet |
|---|---|---|
| `store` | `dbal` | L'endroit où vit la réserve : `dbal`, six tables d'une connexion DBAL ; `memory`, les adaptateurs en mémoire de `phil/stockroom`, vidés entre les requêtes d'un worker — pour les tests et les exécutions uniques. |
| `connection` | `default` | Le nom de la connexion DoctrineBundle où sont les tables : de 1 à 64 lettres, chiffres ou `_`. Le service est `doctrine.dbal.<name>_connection`. |
| `table_prefix` | `phil_stockroom_` | Le préfixe des six noms de table : de 1 à 40 lettres minuscules, chiffres ou `_`, ne commençant pas par un chiffre. Il est vérifié à la construction du conteneur, et de nouveau avant d'atteindre le SQL. |
| `clock` | `clock` | L'identifiant du service de l'horloge PSR-20 qui date les mouvements ; l'horloge de Symfony par défaut. |

## La connexion et les transactions

Les transactions de la réserve s'exécutent sur la connexion de ses tables. Gardez les tables sur la connexion des autres
écritures de l'application — la connexion par défaut, le plus souvent — quand un mouvement doit être conservé avec
elles : la transaction propre à l'application sur cette connexion est rejointe par celle de la réserve, et
`phil/library-bundle` n'exécute chaque prêt et son mouvement dans une seule transaction que dans ce cas. Sur une
connexion à part, la réserve valide ses mouvements séparément.

## Dans les tests

```yaml
# config/packages/test/phil_stockroom.yaml
phil_stockroom:
    store: memory
```

Les stockages en mémoire respectent le même contrat que les tables, et `StockroomTransactions` exécute alors son travail
sans transaction. Un test des tables elles-mêmes garde `dbal`, sur SQLite ou un serveur de test.
