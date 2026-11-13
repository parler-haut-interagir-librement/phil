# Storage

| Store | For |
|---|---|
| `InMemoryInvitationStore` | Tests and scripts that invite and answer in one run |
| `Dbal\DbalInvitationStore` | Two tables of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite |

## The tables

```php
use Phil\AccessLinks\Dbal\DbalInvitationStore;

$store = new DbalInvitationStore($connection);   // phil_access_link_invitation and phil_access_link
$store = new DbalInvitationStore($connection, 'rsvp_invitation', 'rsvp_link');
```

`configureSchema(Schema $schema): Schema` adds the tables to a schema that lacks them: call it from a Doctrine
migration, or from a listener of the ORM's `postGenerateSchema` event so that `doctrine:migrations:diff` writes them.

| Table | Columns |
|---|---|
| `phil_access_link_invitation` | `id` (key), `campaign` and `recipient` (unique together), `created_at`, `valid_from`, `closes_at`, `expires_at`, `answer`, `responded_at`, `answered_at`, `reminders_sent`, `next_reminder_at` (indexed), `revoked_at`, `links_issued` |
| `phil_access_link` | `hash` (key), `invitation_id` (indexed) |

Instants are Unix timestamps. A table name is checked before any SQL: up to 63 lowercase letters, digits or `_`, not
starting with a digit.

## The atomic writes

| Change | The write |
|---|---|
| An invitation | One transaction: the invitation, then its first link; a recipient invited before leaves nothing |
| A link | One transaction: `links_issued` raised only below the most, then the link |
| An answer | One `UPDATE` where the invitation is not revoked, valid and not closed at that moment |
| A reminder | One `UPDATE` where the count of reminders is the one read, with no answer, no revocation, before the closure and once due |
| A revocation | One `UPDATE` where the invitation is not revoked yet |

The answer is the application's data, kept in the `answer` column; the recipient is a stable identifier. Deleting a
member's data is deleting their invitations by that identifier, which deletes nothing else.
