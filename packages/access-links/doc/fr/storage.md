<!-- phil-dev source-sha256=afe076e8f1e99fea4429f3f8ba695fcc7abe6cbe94164e23e94cd5307ea9bc7a -->
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
| `phil_access_link_invitation` | `id` (clé), `campaign` et `recipient` (uniques ensemble), `created_at`, `valid_from`, `closes_at`, `expires_at`, `answer`, `responded_at`, `answered_at`, `reminders_sent`, `next_reminder_at` (indexée), `revoked_at`, `links_issued` |
| `phil_access_link` | `hash` (clé), `invitation_id` (indexée) |

Les instants sont des horodatages Unix. Un nom de table est vérifié avant tout SQL : jusqu'à 63 lettres minuscules,
chiffres ou `_`, sans commencer par un chiffre.

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
