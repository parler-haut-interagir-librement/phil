<!-- phil-dev source-sha256=104d310d00d13cf7bbec1fca80f19288bd9b5f3350778781c45945e54f70f489 -->
# Stockage

Un `Contract\TupleStore` conserve les tuples. Il ne retourne jamais un tuple expiré selon sa propre horloge, borne
chaque lecture par la limite qu'on lui donne, et laisse les règles du modèle à `RelationWriter`. Chaque appel agit dans la partition que nomme sa
`Contract\TupleScope`, et dans aucune autre : voir [Partitions](partitions.md).

| Méthode | Fait |
|---|---|
| `update($delete, $write)` | Supprime puis écrit, d'un seul coup ou pas du tout. |
| `matching($object, $relations, $subjects)` | Les tuples d'un objet parmi certaines relations et certains sujets, exactement. |
| `subjects($object, $relation, $limit, $setsOnly)` | Les tuples d'une relation sur un objet, ordonnés par sujet. |
| `objects($kind, $relation, $subject, $limit)` | Les tuples d'une relation donnée à un sujet, sur des objets d'un type, ordonnés par objet. |
| `forget($object)` | Supprime chaque tuple sur un objet et donné à lui : une ressource supprimée, un membre effacé. |
| `purgeExpired()` | Supprime les tuples expirés de la partition. |

## En mémoire

`InMemoryTupleStore` conserve les tuples dans un tableau, pour les tests et un seul processus, ordonnés comme le
magasin en base de données les ordonne, chaque partition à part. Ils sont perdus avec l'objet.

## Dans une base de données

`Dbal\DbalTupleStore` les conserve dans une table d'une connexion Doctrine DBAL, sur PostgreSQL, MySQL, MariaDB ou
SQLite.

| Colonne | Type |
|---|---|
| `scope` | `VARCHAR(128)`, la partition ; vide, sa valeur par défaut, pour la partition par défaut |
| `object_kind`, `relation`, `subject_kind`, `subject_relation` | `VARCHAR(64)` ; `subject_relation` est vide pour un sujet qui est un objet ou tous les objets |
| `object_id`, `subject_id` | `VARCHAR(255)` ; `*` pour tous les objets d'un type |
| `expires_at` | `BIGINT`, un horodatage Unix, ou null |

| Index | Colonnes | Sert |
|---|---|---|
| Clé primaire | `scope`, `object_kind`, `object_id`, `relation`, `subject_kind`, `subject_id`, `subject_relation` | Une vérification : les tuples d'un objet. |
| `<table>_subject` | `scope`, `subject_kind`, `subject_id`, `subject_relation`, `object_kind`, `relation` | Une liste, et l'oubli d'un membre. |
| `<table>_expires_at` | `scope`, `expires_at` | La purge. |

La table est `phil_relation_tuple`, ou le troisième argument du constructeur : jusqu'à 63 lettres minuscules, chiffres
ou `_`, sans commencer par un chiffre. Le quatrième argument, `scope`, est la `TupleScope` ; `SinglePartition` par défaut.
**Les identifiants se comparent octet par octet** : les colonnes de texte prennent une collation binaire —
`utf8mb4_bin` sur MySQL et MariaDB, `ascii_bin` pour les noms de types et de relations, que leur règle garde en ASCII
minuscule — et la collation `C` sur PostgreSQL, de sorte que `member:Ann` n'est jamais `member:ann`. Sur MySQL, la clé
primaire contient au plus 2 808 octets, dans les 3 072 que peut contenir une clé d'index.

Chaque instruction commence par `scope = ?`, la partition de l'appel. Une mise à jour est une transaction : chaque tuple
supprimé ou écrit est d'abord supprimé par sa clé, puis chaque tuple à écrire est inséré. `matching()` lit au plus 256 relations et 256 sujets. Chaque lecture ajoute
`expires_at IS NULL OR expires_at > ?`, la seconde de l'horloge.

## Le schéma

`configureSchema()` ajoute la table à un schéma qui en est dépourvu. `Dbal\TupleSchemaListener`, sur l'événement
`postGenerateSchema` de l'ORM, l'ajoute au schéma que l'ORM génère pour la connexion du magasin, de sorte que
`doctrine:migrations:diff` écrit la migration qui la crée, ou qui met une table plus ancienne à cette version. Sans l'ORM,
`schemaUpdate()` donne les instructions qui créent la table, modifient une table plus ancienne, ou rien quand elle est à
jour. Voir [Mise à niveau depuis 0.1](partitions.md#mise-à-niveau-depuis-01).

## La purge

Les tuples expirés ne sont jamais lus, mais restent dans la table jusqu'à ce que `purgeExpired()` les supprime, dans la partition de
l'appel ; une tâche planifiée l'appelle pour chaque partition, dont la commande `phil:access:purge` de
`phil/access-bundle`.

## Longues listes

Une vérification lit par la clé primaire ; une liste, par l'index des sujets, au plus la limite demandée plus un par
lecture. Une longue liste de documents partagés avec un membre, ou de membres d'un groupe, n'est jamais chargée en
entier.
