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

A delivery is created by an insert that leaves a row of the same id alone — `ON CONFLICT DO NOTHING` on PostgreSQL,
`INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite —, so that one campaign queues one message per
subscriber. A count of clicks or reasons is an insert of a zero row unless it is there, then one `UPDATE` that adds
one, so that no click is lost to another at the same moment.

The tables hold no IP address and no user agent; the reasons to leave and the clicks in a total name nobody.
