<!-- phil-dev source-sha256=1b6b939d918bba8a5c31f936945b390714122f7b5360ad6872ec528932de91cc -->
# Référence

Chaque type public du paquet, avec son rôle.

## Classes

| Type | Genre | Rôle |
|---|---|---|
| `Phil\StockroomBundle\PhilStockroomBundle` | `final class` | Le bundle : sa configuration `phil_stockroom` et ses services. |
| `Phil\StockroomBundle\Infrastructure\DependencyInjection\StockroomServices` | `final class` | Les services de la configuration : les stockages, les services de la réserve, les transactions, l'écouteur de schéma et la commande. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables` | `final readonly class` | Les six noms de table faits d'un même préfixe, et leurs définitions ; `configureSchema()` ajoute les absentes à un schéma. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers` | `final readonly class` | `Containers` sur une connexion DBAL. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalItems` | `final readonly class` | `Items` sur une connexion DBAL ; codes uniques. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances` | `final readonly class` | `Balances` sur une connexion DBAL ; une quantité de zéro supprime sa ligne. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal` | `final readonly class` | `Journal` sur une connexion DBAL, écrit une seule fois, lu du plus récent au plus ancien. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists` | `final readonly class` | `LoadLists` sur une connexion DBAL ; une liste remplacée dans une seule transaction. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | `final readonly class` | `run(Closure $work, string ...$items)` : une transaction, les articles verrouillés d'abord, celle de l'appelant rejointe ; `lockStatement()` ; `uses(Connection)`. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomSchemaListener` | `final readonly class` | Ajoute les tables au schéma que génère l'ORM pour la connexion du stockage. |
| `Phil\StockroomBundle\Infrastructure\Dbal\Row` | `final readonly class`, interne | Une ligne relue avec ses types ; les moments en microsecondes UTC. |
| `Phil\StockroomBundle\Infrastructure\Console\SchemaCommand` | `final readonly class` | `phil:stockroom:schema`. |

## Constantes

| Constante | Valeur | Sens |
|---|---|---|
| `StockroomTables::DEFAULT_PREFIX` | `phil_stockroom_` | Le préfixe par défaut des tables. |
| `StockroomTables::PREFIX_PATTERN` | `/^[a-z_][a-z0-9_]{0,39}$/D` | La forme d'un préfixe. |
| `StockroomTables::KEY_LENGTH` | `140` | La longueur d'une colonne d'identifiant ou de clé de lieu. |
| `StockroomTransactions::MAX_ITEMS` | `500` | Le plus grand nombre d'articles qu'une transaction verrouille. |
| `StockroomServices::STORE_DBAL`, `STORE_MEMORY` | `dbal`, `memory` | Les valeurs de `store`. |
| `StockroomServices::CONNECTION` | `phil_stockroom.connection` | Le paramètre de l'identifiant du service de la connexion. |

## Services et paramètres

| Identifiant | Ce que c'est |
|---|---|
| `Phil\Stockroom\Stockroom`, `Phil\Stockroom\Deployments`, `Phil\Stockroom\LoadListReader` | Services publics de la bibliothèque sur les stockages configurés. |
| `Phil\Stockroom\Contract\StockReader` | Alias public de `Stockroom`. |
| `Phil\Stockroom\Contract\Containers`, `Items`, `Balances`, `Journal`, `LoadLists` | Alias des adaptateurs configurés. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | Public. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables` | Les tables du préfixe configuré. |
| `phil_stockroom.connection` | Paramètre : `doctrine.dbal.<connection>_connection`, ou `null` en mémoire. |

## Commande

| Commande | Ce qu'elle fait |
|---|---|
| `phil:stockroom:schema [--force]` | Montre le SQL qui crée les tables de la réserve absentes de sa connexion ; l'exécute avec `--force`. Enregistrée avec le stockage `dbal` quand Console est installé. |

## Exceptions

| Exception | Quand |
|---|---|
| `InvalidArgumentException` | Un préfixe de table qui n'en est pas un ; une transaction qui nomme plus de 500 articles. |
| `Phil\Stockroom\Exception\InvalidStock` | Une ligne qui n'est pas ce que garde la bibliothèque. |
| `UnexpectedValueException` | Une colonne du mauvais type, ou un genre, un suivi ou un état qui n'en est pas un. |
| `Doctrine\DBAL\Exception` | La base de données échoue ; `UniqueConstraintViolationException` pour un code porté par deux articles, ou un mouvement écrit deux fois. |
