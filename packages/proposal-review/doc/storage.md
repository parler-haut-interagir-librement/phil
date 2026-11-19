# Storage

The engine reads and writes through four ports under `Contract\`. The package ships them in memory, under `Memory\`,
for tests and demonstrations, and on Doctrine DBAL, under `Dbal\`, for a database; an application may also implement
them on its own storage — another database, an API.

| Port | Keeps | In memory | On Doctrine DBAL |
|---|---|---|---|
| `CampaignStore` | Campaigns, with the last phase `advance()` recorded | `InMemoryCampaignStore` | `DbalCampaignStore` |
| `ProposalStore` | Proposals, the reviewers assigned to each, the conflicts declared | `InMemoryProposalStore` | `DbalProposalStore` |
| `ReviewStore` | Reviews, one per reviewer and proposal; decisions, one per proposal | `InMemoryReviewStore` | `DbalReviewStore` |
| `VoteLog` | The pairwise votes of each campaign, numbered | `InMemoryVoteLog` | `DbalVoteLog` |

## The DBAL stores

Each store takes the application's `Doctrine\DBAL\Connection` and a table prefix, `phil_review_` by default: up to 40
lowercase letters, digits or `_`, checked before it reaches SQL. They run on MySQL, MariaDB, PostgreSQL and SQLite.

### The tables

Each store adds its tables to a schema with `configureSchema()`, and leaves a table already there as it is. A migration
builds them from that schema:

```php
use Doctrine\DBAL\Schema\Schema;

$schema = Schema::editor()->create();

foreach ([$campaigns, $proposals, $reviews, $votes] as $store) {
    $schema = $store->configureSchema($schema);
}

foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

| Table | Primary key | Holds |
|---|---|---|
| `campaign` | `id` | The schedule, criteria and policy as one JSON document; the last phase recorded |
| `proposal` | `campaign`, `id` | The authors as a JSON list |
| `assignment` | `campaign`, `proposal`, `reviewer` | One row per reviewer assigned |
| `conflict` | `campaign`, `reviewer`, `kind`, `target` | A conflict with a proposal (`kind` `proposal`) or with an author (`author`) |
| `review` | `campaign`, `proposal`, `reviewer` | The scores as a JSON list; the instant |
| `decision` | `campaign`, `proposal` | The decision, who recorded it, when; when it was applied, empty while pending |
| `vote` | `campaign`, `sequence` | The voter, the winner, the loser and the instant |

### What they guarantee

- Identifiers are compared byte for byte: `CFP` and `cfp`, `café` and `cafe` are different campaigns, on MySQL too,
  where the identifier columns take the `utf8mb4_bin` collation. An identifier kept in a database is valid UTF-8 and
  does not end with a space, which MySQL would ignore when it compares; a write refuses one with `InvalidInput`, and a
  read for one finds nothing.
- Instants are written as text in UTC, `2026-11-12T10:00:00.123456Z`: exact to the microsecond, sorted as text, the
  same on every platform. An instant outside the years 1 to 9999 is refused.
- Lists come back in the orders the ports promise, sorted in PHP by byte order, whatever the database's collation.
- A save replaces the row in a transaction. An assignment or a conflict already there, even one written by another
  request between the read and the insert, is kept once.
- A vote takes the number after the campaign's last. When another request took it first, the primary key refuses the
  row, inside a savepoint when the caller has a transaction open, and the vote takes the next number, up to
  `DbalVoteLog::MAX_ATTEMPTS` times; after that, the `UniqueConstraintViolationException` reaches the caller.
- A row the store did not write — a phase, a decision or a kind it does not know, JSON that is not what it wrote, a
  number that is not whole — is refused with an `UnexpectedValueException`, never read as something else.

## What another adapter keeps

- Identifiers as strings, exactly as given: `'7'` and `7` are not the same proposal to an application, and a numeric
  identifier must come back a string.
- Instants in UTC, as the value objects give them: `submittedAt`, `recordedAt`, `appliedAt`, `castAt` and the windows
  of a schedule.
- The orders the ports promise: proposals and decisions by proposal identifier, reviews by reviewer identifier,
  reviewers by identifier, votes by number.
- Replacement where the port says so: a review replaces the same reviewer's review of the same proposal, a decision
  replaces the proposal's decision, an assignment or a conflict kept twice is kept once.
- The vote log append-only and numbered from 1 per campaign, without gaps, even when two votes arrive at once: in a
  database, a sequence per campaign under a lock, or a unique key on (campaign, number) and a retry.

A `Campaign` holds its schedule, criteria and policy as value objects; an adapter may store them as JSON and rebuild
them, or as rows. `Campaign::withObservedPhase()` gives the campaign to save after `advance()`.

## Transactions

The engine makes one write per action, except `applyDecisions()`, which saves each pending decision in turn and
dispatches `DecisionsApplied` after the last. An application that needs all or nothing wraps the call in its own
transaction, and has its listeners act once the transaction is committed — for example by dispatching a Messenger
message that carries the campaign's identifier.
