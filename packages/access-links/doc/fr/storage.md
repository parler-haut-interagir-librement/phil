<!-- phil-dev source-sha256=524418b5890c663e9dcc4d36ab9eec4b14aa6b315a0b9425891957cfa7399a5a -->
# Stockage

| Magasin | Pour |
|---|---|
| `InMemoryInvitationStore` | Les tests et les scripts qui invitent et reçoivent des réponses dans une même exécution |
| `Dbal\DbalInvitationStore` | Deux tables d'une connexion Doctrine DBAL sur PostgreSQL, MySQL, MariaDB ou SQLite |

## Les tables

```php
use Phil\AccessLinks\Dbal\DbalInvitationStore;

$store = new DbalInvitationStore($connection);   // phil_access_link_invitation and phil_access_link
$store = new DbalInvitationStore($connection, 'rsvp_invitation', 'rsvp_link');
```

`configureSchema(Schema $schema): Schema` ajoute les tables à un schéma qui ne les a pas : appelez-la depuis une
migration Doctrine, ou depuis un écouteur de l'événement `postGenerateSchema` de l'ORM pour que
`doctrine:migrations:diff` les écrive.

| Table | Colonnes |
|---|---|
| `phil_access_link_invitation` | `id` (clé), `campaign` et `recipient` (uniques ensemble, octet par octet), `created_at`, `valid_from`, `closes_at`, `expires_at`, `answer`, `responded_at`, `answered_at`, `reminders_sent`, `next_reminder_at` (indexée), `revoked_at`, `links_issued` |
| `phil_access_link` | `hash` (clé), `invitation_id` (indexée) |

Les instants sont des horodatages Unix. Un nom de table est vérifié avant tout SQL : jusqu'à 63 lettres minuscules,
chiffres ou `_`, sans commencer par un chiffre.

## Identité des campagnes et des destinataires

**Les identifiants se comparent octet par octet**, comme la bibliothèque les compare en PHP : `Spring-Gala` et
`spring-gala` sont deux campagnes, `Member-7`, `member-7` et `membér-7` trois destinataires, chacun invité une fois par
campagne. Le magasin met la base de données d'accord :

| Plateforme | `id`, `campaign`, `recipient`, `hash`, `invitation_id` |
|---|---|
| MySQL, MariaDB | `utf8mb4` avec la collation `utf8mb4_bin`, selon la plateforme de la connexion |
| PostgreSQL | La collation de la base de données : les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont |
| SQLite | La collation `BINARY` par défaut |

Sans cela, la collation par défaut de MySQL 8 (`utf8mb4_0900_ai_ci`) ignore la casse et les accents, et la contrainte
d'unicité sur `campaign` et `recipient` refusait l'invitation de `Member-7` une fois `member-7` invité.

Une application dont les identifiants ne tiennent pas compte de la casse — un destinataire qui est un nom de compte
saisi par des gens, par exemple — les normalise, avec `mb_strtolower()` ou la forme sous laquelle elle range le compte,
avant d'inviter ou de compter : la bibliothèque ne met jamais la casse de côté d'elle-même.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion. Amenez les cinq colonnes
à la collation binaire avec une migration ; avec l'écouteur `postGenerateSchema` de l'ORM en place,
`doctrine:migrations:diff` l'écrit, ou à la main (avec les noms de table par défaut) :

```sql
ALTER TABLE phil_access_link_invitation
    MODIFY id VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY campaign VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY recipient VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE phil_access_link
    MODIFY hash VARCHAR(81) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY invitation_id VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
```

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise la contrainte d'unicité.
Sur PostgreSQL et SQLite, rien ne change.

## Les écritures atomiques

| Changement | L'écriture |
|---|---|
| Une invitation | Une transaction : l'invitation, puis son premier lien ; un destinataire déjà invité ne laisse rien |
| Un lien | Une transaction : `links_issued` augmenté seulement sous le maximum, puis le lien |
| Une réponse | Un seul `UPDATE` où l'invitation n'est pas révoquée, est valide et n'est pas close à cet instant |
| Un rappel | Un seul `UPDATE` où le compte de rappels est celui lu, sans réponse, sans révocation, avant la clôture et une fois échu |
| Une révocation | Un seul `UPDATE` où l'invitation n'est pas encore révoquée |

La réponse est une donnée de l'application, conservée dans la colonne `answer` ; le destinataire est un identifiant
stable. Effacer les données d'un membre, c'est supprimer ses invitations selon cet identifiant, ce qui ne supprime rien
d'autre.
