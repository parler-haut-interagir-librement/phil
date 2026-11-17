<!-- phil-dev source-sha256=435fd485fcf9ebd3f779db7d7571b18974fa146c9f1516337c0be1a093da4e42 -->
# Référence

## Le bundle

| Classe | Rôle |
|---|---|
| `PhilOutboxBundle` | La configuration `phil_outbox` |
| `Infrastructure\DependencyInjection\OutboxServices` | Les services de la configuration ; `STORE_DBAL`, `STORE_MEMORY`, `CODEC_TAG`, `DISPATCHER` |

## Doctrine

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\DbalOutboxStore` | Les enregistrements dans une table d'une connexion DBAL, joints à la transaction de l'appelant ; une réservation qui prend ses enregistrements à bail en un seul `UPDATE` |
| `Infrastructure\Doctrine\OutboxTable` | Le nom et la définition de la table, les collations d'octets des clés |
| `Infrastructure\Doctrine\OutboxSchemaListener` | Ajoute la table au schéma que l'ORM génère |
| `Infrastructure\Doctrine\Rows` | Les colonnes d'une ligne sous forme de types PHP |

## Console et workers

| Classe | Rôle |
|---|---|
| `Infrastructure\Console\RelayCommand` | `phil:outbox:relay` |
| `Infrastructure\Console\DeadShowCommand` | `phil:outbox:dead:show` |
| `Infrastructure\Console\DeadRetryCommand` | `phil:outbox:dead:retry` |
| `Infrastructure\Console\DeadRemoveCommand` | `phil:outbox:dead:remove` |
| `Infrastructure\Console\PurgeCommand` | `phil:outbox:purge` |
| `Infrastructure\Console\SchemaCommand` | `phil:outbox:schema` |
| `Infrastructure\Worker\RelayRunner` | Exécute le relais lot après lot, sous le verrou `phil_outbox.relay` |

## Le planificateur et Messenger

| Classe | Rôle |
|---|---|
| `Infrastructure\Scheduler\OutboxSchedule` | L'horaire `phil_outbox` |
| `Infrastructure\Scheduler\RelayOutbox` | Son message, qui ne porte rien |
| `Infrastructure\Scheduler\RelayOutboxHandler` | Relaie à chaque tic |
| `Infrastructure\Messenger\MessengerDispatcher` | Le répartiteur PSR-14 qui relaie vers un bus de Messenger |
