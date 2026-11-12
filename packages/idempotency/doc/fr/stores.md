<!-- phil-dev source-sha256=c8f86ad793a0af068f1ca6acb153b01508d93854e25563c93e436b0e02900979 -->
# Stockages

Un stockage implémente `Contract\IdempotencyStore`. La bibliothèque en fournit deux ; les deux gardent l'empreinte,
jamais la charge, et les deux oublient d'eux-mêmes un enregistrement expiré.

## `Store\DbalIdempotencyStore`

```php
new DbalIdempotencyStore(Connection $connection, ClockInterface $clock)
```

Les clés vivent dans la table `phil_idempotency` (`Store\IdempotencyTable::NAME`) : la portée, la clé, l'empreinte,
l'état, le statut, les en-têtes et le corps de la réponse, et l'expiration, avec une clé primaire sur la portée et la
clé et un index sur l'expiration.

- **La réservation est atomique** : elle supprime la ligne expirée ou échouée de la clé, puis insère la clé si elle
  n'y est pas, en une instruction qui laisse une ligne vivante tranquille sans interrompre la transaction —
  `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite.
  Ailleurs, une insertion simple dont la violation d'unicité est une réservation perdue.
- **Les expirations sont écrites en UTC** et relues en UTC, quel que soit le fuseau de PHP. Une ligne expirée n'est
  jamais lue.
- `purge(): int` oublie les lignes expirées ; lancez-la depuis une tâche planifiée.
- `IdempotencyTable::configure(Schema $schema, ?AbstractPlatform $platform = null): Schema` ajoute la table à un
  schéma, pour une migration ou un écouteur de schéma ; un schéma qui l'a déjà est retourné tel quel. Portées et clés
  se comparent octet par octet : avec une plateforme MySQL ou MariaDB, dont les collations par défaut confondent casse
  et accents, les deux colonnes prennent `utf8mb4_bin`.

## `Store\CacheIdempotencyStore`

```php
new CacheIdempotencyStore(CacheItemPoolInterface $pool, LockFactory $locks, ClockInterface $clock)
```

Les clés vivent dans un pool PSR-6. Un pool n'a pas d'insertion atomique : chaque écriture prend le verrou de sa clé
auprès du composant Lock de Symfony, pendant au plus 10 secondes, et la lecture et l'écriture d'une réservation ne
font qu'une étape.

- **Le pool et le stockage des verrous sont partagés** par chaque serveur qui répond aux mêmes clés : Redis,
  Memcached, une base de données. Un système de fichiers local derrière un répartiteur de charge laisse deux serveurs
  réserver la même clé.
- Chaque entrée ne contient que des scalaires, jamais un objet, sous `phil_idempotency.` et un SHA-256 de la portée et
  de la clé : une portée ou une clé peut contenir des caractères que PSR-6 réserve.
- Chaque entrée expire avec son enregistrement : le pool l'oublie de lui-même. L'expiration de l'enregistrement est
  aussi vérifiée contre l'horloge de l'application.

## Lequel choisir

La table est le choix par défaut : durable, atomique sans verrou, et dans la transaction de l'opération quand la
connexion est partagée. Le cache convient aux clés qui peuvent se perdre avec le cache, comme le double envoi d'un
formulaire, ou à une application sans base de données.

Ensuite : [Messenger](messenger.md).
