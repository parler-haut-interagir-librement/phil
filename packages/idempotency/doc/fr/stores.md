<!-- phil-dev source-sha256=1a3d05ae6172be2164e84c14a262387e73bf02afe1b73a62179ba14a780673ce -->
# Stockages

Un stockage implémente `Contract\IdempotencyStore`. La bibliothèque en fournit deux ; les deux gardent l'empreinte,
jamais la charge, et les deux oublient d'eux-mêmes un enregistrement expiré.

## `Store\DbalIdempotencyStore`

```php
new DbalIdempotencyStore(Connection $connection, ClockInterface $clock)
```

Les clés vivent dans la table `phil_idempotency` (`Store\IdempotencyTable::NAME`) : la portée, la clé, l'empreinte,
l'état, le statut, les en-têtes et le corps de la réponse, l'expiration, et le jeton de la réservation qui tient la
clé, avec une clé primaire sur la portée et la clé et un index sur l'expiration.

- **La réservation est atomique** : elle supprime la ligne expirée ou échouée de la clé, puis insère la clé si elle
  n'y est pas, en une instruction qui laisse une ligne vivante tranquille sans interrompre la transaction —
  `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite.
  Ailleurs, une insertion simple dont la violation d'unicité est une réservation perdue.
- **Seul le détenteur écrit l'issue** : `complete()` et `fail()` sont un seul `UPDATE … WHERE token = ?`. Une
  exécution dont une autre a remplacé la réservation ne touche aucune ligne et reçoit `ReservationLost`. Là où le
  pilote compte les lignes changées plutôt que les lignes trouvées, comme MySQL, une mise à jour qui ne change rien se
  distingue d'une réservation perdue en relisant le jeton.
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
- **Seul le détenteur écrit l'issue** : chaque entrée garde le jeton de sa réservation, et `complete()` et `fail()` le
  lisent et écrivent sous le même verrou, en levant `ReservationLost` quand l'entrée porte un autre jeton ou aucun.
  PSR-6 n'a pas de comparer-et-écrire : la vérification tient parce que chaque écriture du stockage prend le verrou de
  sa clé, et tant qu'une écriture se termine dans les 10 secondes de ce verrou.
- Une réservation passée son expiration peut déjà avoir quitté le pool, contrairement à une ligne de la table : sa
  terminaison est alors perdue elle aussi, et signalée comme telle.

## Lequel choisir

La table est le choix par défaut : durable, atomique sans verrou, et dans la transaction de l'opération quand la
connexion est partagée. Le cache convient aux clés qui peuvent se perdre avec le cache, comme le double envoi d'un
formulaire, ou à une application sans base de données.

Ensuite : [Messenger](messenger.md).
