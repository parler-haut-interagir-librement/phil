# Storage

The bundle keeps its data in tables of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite. Instants
are Unix timestamps.

| Table | Holds |
|---|---|
| `phil_newsletter_list` | The lists, by site and key |
| `phil_newsletter_subscriber` | The subscribers: address, external id, state, dates, soft bounces |
| `phil_newsletter_membership` | Each subscriber's place on each list |
| `phil_newsletter_campaign` | The campaigns, with their author and approval |
| `phil_newsletter_link` | The links each campaign tracks, by index |
| `phil_newsletter_delivery` | One row per message to one subscriber, with the send run that queued it |
| `phil_newsletter_template` | The templates of administrators and members; a shared one has an empty site |
| `phil_newsletter_click` | The clicks of each link, in a total |
| `phil_newsletter_recipient_click` | The clicks per recipient, for the campaigns that chose them |
| `phil_newsletter_unsubscribe_reason` | The reasons to leave, a total per site, list, reason and day |
| `phil_newsletter_suppression` | The suppressed addresses, as keyed hashes |
| `phil_newsletter_send_run` | One row per attempt at sending a campaign: its state, its counts and its dates |

```php
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;

$schema = NewsletterSchema::configure($schema);
```

`configure()` adds the tables a schema lacks, and the columns and indexes its tables of the newsletter lack. With
DoctrineBundle and the ORM, `NewsletterSchemaListener` adds them to the schema the ORM generates for the bundle's
connection, and `doctrine:migrations:diff` writes the migration. Without the ORM, a migration compares the schema
introspected with the same schema configured:

```php
$manager = $connection->createSchemaManager();
$manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema(), $connection->getDatabasePlatform()));
```

A column that a version adds to an existing table accepts a null, so that it is added to a table that holds rows.

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
| `phil_newsletter_delivery` | `id`, `subscriber_id`, `campaign_id`, `run_id` |
| `phil_newsletter_template` | `site`, `template_key` |
| `phil_newsletter_click` | `campaign_id` |
| `phil_newsletter_recipient_click` | `subscriber_id`, `campaign_id` |
| `phil_newsletter_unsubscribe_reason` | `site`, `list_id` |
| `phil_newsletter_suppression` | `site`, `email_hash` |
| `phil_newsletter_send_run` | `id`, `campaign_id` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.2

Version 0.3 adds the table `phil_newsletter_send_run`, the columns `author`, `approved_by` and `approved_at` of
`phil_newsletter_campaign`, and the column `run_id` of `phil_newsletter_delivery` with the index
`phil_newsletter_delivery_i2` (`run_id`, `status`, `id`). Each new column accepts a null: the rows already there keep
theirs, as campaigns of no author nor approval and deliveries of no send run. With the ORM, `doctrine:migrations:diff`
writes the migration; without it, `migrateSchema()` above does, on MySQL, MariaDB, PostgreSQL and SQLite alike. Both
are tested on MySQL 8.4 and PostgreSQL 17 from the tables of 0.2 holding rows.

A campaign of 0.2 that is a draft or scheduled awaits its approval under `approval.required`, on by default: approve
it, or set `approval.required: false` to send without one. See [Upgrading to 0.3](campaigns.md#upgrading-to-03).

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

A delivery and a send run are each created by an insert that leaves a row of the same id alone — `ON CONFLICT DO
NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite —, so that one campaign
queues one message per subscriber and keeps one run of each number. A count of a send run is one `UPDATE` that adds to
its column, and its state changes by an `UPDATE` that names the state it changes from. A count of clicks or reasons is
an insert of a zero row unless it is there, then one `UPDATE` that adds one, so that no click is lost to another at the
same moment.

The tables hold no IP address and no user agent; the reasons to leave and the clicks in a total name nobody. The
author and the approver of a campaign are user identifiers of the team, not of subscribers.
