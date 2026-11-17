<!-- phil-dev source-sha256=1564a8356267d39d528d143217f77dd98441c70aa4421d13cf59888f04503099 -->
# Entrepôts

Un `OutboxStore` conserve les enregistrements. Un entrepôt qui écrit dans la base de données de la bibliothèque qui
enregistre ajoute un enregistrement dans la transaction de l'écriture propre à la bibliothèque, de sorte que les deux
sont validés ou qu'aucun ne l'est : l'entrepôt partage la connexion de la bibliothèque.

| Entrepôt | Où |
|---|---|
| `Memory\InMemoryOutboxStore` | la mémoire d'un seul processus, pour les tests et les exécutions uniques ; `reset()` oublie tout |
| `DbalOutboxStore` de phil/outbox-bundle | une table d'une connexion Doctrine DBAL, sur SQLite, MySQL, MariaDB et PostgreSQL |

## Le contrat

| Méthode | Promesse |
|---|---|
| `add(OutboxRecord $record): RecordId` | Ajoute un enregistrement en attente ; un enregistrement de même type et de même clé d'idempotence déjà conservé est laissé tel quel et son identifiant est retourné, sans interrompre la transaction de l'appelant |
| `claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array` | Prend à bail les enregistrements disponibles, dans l'ordre où ils ont été ajoutés ; deux réservations n'obtiennent jamais le même enregistrement tant que son bail court ; un enregistrement attend tant qu'un enregistrement antérieur de sa clé d'ordre est en attente ou mort |
| `markDelivered()`, `release()`, `markDead()` | Règlent un enregistrement de la réservation, et refusent quand une autre réservation l'a repris |
| `find()`, `list()`, `count()` | Lisent les enregistrements |
| `revive()`, `discard()` | N'agissent que sur un enregistrement mort |
| `purgeDelivered(DateTimeImmutable $before, int $limit): int` | Retire les enregistrements livrés réglés avant `$before`, dans l'ordre où ils ont été ajoutés |

Les instants sont conservés à la seconde près. Un entrepôt ne garde aucun état entre des requêtes qu'un *worker*
porterait de l'une à l'autre.

## Les tests de contrat

`Phil\Outbox\Test\OutboxStoreTestCase` contient les tests que tout entrepôt réussit. Le test d'un adaptateur l'étend et
donne un entrepôt vide à `createStore()` :

```php
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Test\OutboxStoreTestCase;

final class RedisOutboxStoreTest extends OutboxStoreTestCase
{
    protected function createStore(): OutboxStore
    {
        return new RedisOutboxStore($this->freshConnection());
    }
}
```

Les tests couvrent l'aller-retour de chaque champ, la clé d'idempotence, l'ordre et la limite d'une réservation, les
baux, l'ordre d'une clé, les marques sous une autre réservation, les enregistrements morts, les listes et la purge.
Ajoutez les tests de ce que seul l'adaptateur montre : la transaction qu'il rejoint, les collations de la plateforme.
