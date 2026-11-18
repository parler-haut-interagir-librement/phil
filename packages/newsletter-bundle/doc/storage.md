# Storage

The bundle keeps its data in tables of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite. Instants
are Unix timestamps.

| Table | Holds |
|---|---|
| `phil_newsletter_list` | The lists, by site and key |
| `phil_newsletter_subscriber` | The subscribers: address, external id, state, dates, soft bounces |
| `phil_newsletter_membership` | Each subscriber's place on each list |
| `phil_newsletter_campaign` | The campaigns |
| `phil_newsletter_link` | The links each campaign tracks, by index |
| `phil_newsletter_delivery` | One row per message to one subscriber |
| `phil_newsletter_template` | The templates of administrators and members; a shared one has an empty site |
| `phil_newsletter_click` | The clicks of each link, in a total |
| `phil_newsletter_recipient_click` | The clicks per recipient, for the campaigns that chose them |
| `phil_newsletter_unsubscribe_reason` | The reasons to leave, a total per site, list, reason and day |
| `phil_newsletter_suppression` | The suppressed addresses, as keyed hashes |

```php
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;

$schema = NewsletterSchema::configure($schema);
```

`configure()` adds the tables a schema lacks. With DoctrineBundle and the ORM, `NewsletterSchemaListener` adds them to
the schema the ORM generates for the bundle's connection.

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. An address is case-insensitive by its key: `Subscriber::keyOf()`
trims and lowercases it before it is stored or looked up, so that `Ann@Example.com` and `ann@example.com` are one
subscriber. Accents are another matter: `muller@example.com` and `müller@example.com` are two mailboxes, and a
collation that folds accents would make them one. The sites, keys and identifiers are compared as they are written:
the site `Acme` is not `acme`.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_newsletter_list` | `id`, `site`, `list_key` |
| `phil_newsletter_subscriber` | `id`, `site`, `email_key`, `external_id` |
| `phil_newsletter_membership` | `subscriber_id`, `list_id` |
| `phil_newsletter_campaign` | `id`, `site`, `list_id` |
| `phil_newsletter_link` | `campaign_id`, `url_hash` |
| `phil_newsletter_delivery` | `id`, `subscriber_id`, `campaign_id` |
| `phil_newsletter_template` | `site`, `template_key` |
| `phil_newsletter_click` | `campaign_id` |
| `phil_newsletter_recipient_click` | `subscriber_id`, `campaign_id` |
| `phil_newsletter_unsubscribe_reason` | `site`, `list_id` |
| `phil_newsletter_suppression` | `site`, `email_hash` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

A delivery is created by an insert that leaves a row of the same id alone — `ON CONFLICT DO NOTHING` on PostgreSQL,
`INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite —, so that one campaign queues one message per
subscriber. A count of clicks or reasons is an insert of a zero row unless it is there, then one `UPDATE` that adds
one, so that no click is lost to another at the same moment.

The tables hold no IP address and no user agent; the reasons to leave and the clicks in a total name nobody.
