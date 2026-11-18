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
| `phil_access_link_invitation` | `id` (key), `campaign` and `recipient` (unique together, byte by byte), `created_at`, `valid_from`, `closes_at`, `expires_at`, `answer`, `responded_at`, `answered_at`, `reminders_sent`, `next_reminder_at` (indexed), `revoked_at`, `links_issued` |
| `phil_access_link` | `hash` (key), `invitation_id` (indexed) |

Instants are Unix timestamps. A table name is checked before any SQL: up to 63 lowercase letters, digits or `_`, not
starting with a digit.

## Identity of campaigns and recipients

**Identifiers compare byte by byte**, as the library compares them in PHP: `Spring-Gala` and `spring-gala` are two
campaigns, `Member-7`, `member-7` and `membér-7` three recipients, each invited once per campaign. The store makes the
database agree:

| Platform | `id`, `campaign`, `recipient`, `hash`, `invitation_id` |
|---|---|
| MySQL, MariaDB | `utf8mb4` with the `utf8mb4_bin` collation, given the connection's platform |
| PostgreSQL | The database's collation: PostgreSQL's default collations are deterministic, equal only when the bytes are |
| SQLite | The default `BINARY` collation |

Without it, the default collation of MySQL 8 (`utf8mb4_0900_ai_ci`) folds case and accents, and the unique constraint
on `campaign` and `recipient` refused the invitation of `Member-7` once `member-7` was invited.

An application whose identifiers are case-insensitive — a recipient that is an account name typed by people, say —
normalizes them, with `mb_strtolower()` or the form it stores the account under, before it invites or counts: the
library never folds case on its own.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. Bring the five columns to the binary
collation with a migration; with the ORM's `postGenerateSchema` listener in place, `doctrine:migrations:diff` writes
it, or by hand (with the default table names):

```sql
ALTER TABLE phil_access_link_invitation
    MODIFY id VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY campaign VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY recipient VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE phil_access_link
    MODIFY hash VARCHAR(81) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY invitation_id VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
```

The change only makes values more distinct, so no existing row breaks the unique constraint. On PostgreSQL and SQLite
nothing changes.

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
