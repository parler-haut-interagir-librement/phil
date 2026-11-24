# Reading the log

The bundle reads its own trail back as application services: pages of rows found by who acted, what was done, to
which record, in which tenant and when; the history of one record; one row by its place; every row of a query for an
export; and the state of the hash chain, segment by segment. These are services without screens: the pages of a back
office are `phil/audit-viewer`'s, built on them. The log is read only. Nothing here changes or removes a row, and
going back to an earlier version of a record belongs to the bundle that keeps real versions of it.

## Facts: what a row is indexed by

A row's payload is the bytes of the application's `CanonicalSerializer`, which the bundle cannot read. What it reads
is the row's **facts**, `Domain\Chain\Facts`, given when the row is recorded:

| Fact | Form | Example |
|---|---|---|
| `action` | 1 to 64 lower-case letters, digits, `.`, `_`, `:` or `-`, starting with a letter | `order.paid`, `workflow.order.ship` |
| `actor` | the stable identifier of the account or the process: 1 to 128 letters, digits, `.`, `_`, `:` or `-` | `acct-7`, `01JC…` |
| `subject` | `Subject::of($type, $id)`: the record the event is about, a type the application names and a stable identifier | `order` `42` |
| `tenant` | the reference of a tenant: 1 to 255 bytes of UTF-8, no control character, no surrounding white space | `shop-a` |

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;

$trail->record('orders', ['amount' => '10.00', 'email' => $commitment->bytes], Facts::of(
    action: 'order.paid',
    actor: $account->id(),
    subject: Subject::of('order', (string) $order->id()),
));
```

Each fact is an identifier, never a personal value. An actor refuses `@`: an e-mail address is personal, and it is
not stable. The facts are columns of their own, indexed, and the row hash covers them
([hash-chain.md](hash-chain.md)): a fact changed in the table shows like a changed payload. Since a fact cannot be
erased without breaking the chain, a name or an address never goes in one; it goes in the payload as a commitment
([commitments-and-erasure.md](commitments-and-erasure.md)).

Without facts, `record($segment, $fields)` writes a row found by its segment and its instant only. A discontinuity
row has no facts.

## Reads of sensitive data

The trail records changes; it can also record who **read** a record whose reading matters. A read is an action under
the prefix `Facts::READ_PREFIX`, `read.`, then what was read, and it always names the record read:

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;

$subject = Subject::of('account', $memberId);
$trail->recordRead('members', 'account.profile', $subject, $operator->id(), ['reason' => 'support']);

// The same row, with the facts built by hand:
$trail->record('members', ['reason' => 'support'], Facts::read('account.profile', $subject, $operator->id()));
```

`what` follows the form of an action and holds 59 characters at most, so that the action, with its prefix, holds 64.
`Facts::isRead()` tells a read from a change, and `AuditQuery::reads()` keeps the reads only; it is
`withAction('read.', prefix: true)`, the filter any reader of actions by prefix already offers. The payload holds what
the application keeps about the read — a reason, a screen, the format of an export —, never the data read.

**When to record a read.** Record the reads that someone may later have to account for: a staff member opening a
member's profile, health or payment details, or private messages; an export or a download of personal data; a
document decrypted for a person to see; a search through other people's records by someone outside the support role.
Leave out a member reading their own data, the pages every visitor sees, and the reads a process makes to do its
work: those are the volume, not the exception, and the trail is not an access log.

**What volume to expect.** A read row is an ordinary row: one per read recorded, about the size of the other rows of
the segment, chained, verified and anchored like them. Reads come far more often than changes when they are recorded
broadly; record them per record opened, not per field shown nor per page of a list, and keep them in a segment of
their own (`members` above) so that the chains of changes stay short to verify. A back office where ten staff members
each open a hundred records a day writes about a thousand rows a day, some 365,000 a year: well within a table and
a verification pass, but enough to plan the anchoring and the retention of that segment.

## Exact comparison

A tenant, an actor and a record's identifier are compared byte by byte, so that one tenant never reads another
tenant's trail. The table gives the three columns the binary collation of MySQL and MariaDB (`utf8mb4_bin`) and the
`C` collation of PostgreSQL; SQLite compares them byte by byte already. Under a server's default collation
(`utf8mb4_0900_ai_ci`), `tenant = 'acme'` would also find the rows of `ACME` and `acmé`.

The comparison is made a second time in PHP, with `===`: `DbalAuditLogReader` keeps only the rows that pass every
filter of the query (`AuditQuery::matches()`), and `AuditLog` does the same with whatever reader it is given, after
holding the query to the current tenant. A table whose columns kept a folding collation — created by hand, or before
its columns took the binary one — therefore never leaks a row; its pages may hold fewer rows than their limit, and
the next page still follows the last row read. Such a table is brought to the binary collation by the next schema
difference (`doctrine:migrations:diff`), since the schema listener declares it.

## `AuditQuery`

An immutable query: each method returns a new one.

| Method | Filter |
|---|---|
| `AuditQuery::all()` | Every row, the latest first, 50 a page |
| `AuditQuery::historyOf(Subject)` | The rows about one record, the earliest first |
| `byActor(string)` | One actor |
| `withAction(string, bool $prefix = false)` | One action, or with `$prefix` every action that starts with it, such as `workflow.order.` |
| `reads()` | The reads only, the actions under `read.` |
| `about(Subject)`, `aboutType(string)` | One record, or every record of a type |
| `inSegment(string)` | One segment |
| `inTenant(?string)` | One tenant; `null`, every tenant and none |
| `between(?DateTimeImmutable, ?DateTimeImmutable)` | From the first instant included to the second excluded; either end may be open, and a period that ends before it starts is refused |
| `ordered(Order)` | `Order::NewestFirst` or `Order::OldestFirst` |
| `limitedTo(int)` | 1 to 500 rows a page |
| `after(?Cursor)` | The page after a cursor; `null`, the first page |

Every filter applies. A value that is not an identifier is refused with `InvalidValue` when the query is built, so a
query that exists only holds values the table can hold.

## Pages and cursors

Rows come by instant, then segment, then sequence, so that two rows of the same instant always come in the same
order. `AuditPage` holds the rows of a page and `next`, the `Cursor` of the next page, `null` on the last one. The
next page starts after the key of the last row read, on the index: no offset is counted, and the thousandth page
costs what the first does. A cursor travels as a token, `Cursor::token()`, the instant, segment and sequence joined
by `~`; `Cursor::fromToken()` reads it back and refuses anything else.

```php
$page = $log->page(AuditQuery::all()->withAction('workflow.order.', prefix: true)->limitedTo(100));

foreach ($page->rows as $row) {
    echo $row->instant(), ' ', $row->facts->action, ' ', $row->facts->actor, "\n";
}

$next = $log->page(AuditQuery::all()->withAction('workflow.order.', prefix: true)->limitedTo(100)->after($page->next));
```

## `AuditLog`

The service that reads, public in the container.

| Method | Returns |
|---|---|
| `page(AuditQuery)` | An `AuditPage` |
| `history(Subject, ?Cursor = null, int $limit = 50)` | The history of one record, the earliest first: the changes of state its workflows recorded and every other event about it |
| `row(string $segment, int $sequence)` | One row, or `null` |
| `each(AuditQuery)` | Every row of the query from its cursor on, read 500 at a time: memory holds one page, however long the log |

In a tenant — when the `TenantScope` port gives one, as it does with `phil/tenancy-bundle`
([integrations.md](integrations.md)) — every read is held to that tenant's rows, whatever tenant the query names, and
`row()` answers `null` for a row of another tenant. Outside any tenant the log is read whole.

## The state of the chain: `ChainStatus`

| Method | Returns |
|---|---|
| `segments()` | Every segment, by name, with its head row, read on the key: a `SegmentStatus` whose state is `ChainState::Unverified` |
| `verify(string $segment)` | The segment verified against its anchors, as `phil:audit:verify` does: `Holds`, `HoldsWithExplainedBreaks` or `Broken`, with its breaks (`breaks`, `alarms()`) |

Verifying reads every row of the segment, so a viewer asks for it segment by segment rather than on every page. In a
tenant, both methods see that tenant's site segments only: another site's segment, or a shared one, is not listed,
and verifying it answers a segment without rows ([sites.md](sites.md)). A `SegmentStatus` names its site with
`site()` and the segment the application named with `base()`.

## Adapters of the read side

`Domain\Contract\AuditLogReader` is the port: `page(AuditQuery)` and `row($segment, $sequence)`.

- `DbalAuditLogReader`, registered with a connection, reads the trail's table with DBAL: each filter is an equality on
  an indexed column — the action prefix a `LIKE` that takes `_` literally —, the cursor a condition on the key, and one
  row more than the limit tells whether a next page exists. The cursor's condition opens with a bound on the instant
  alone, which an index ranges over. The table carries an index for each way of reading, each ending with the key of
  the order — `recorded_at, segment, sequence` —, so that a page is neither scanned for nor sorted: on the key alone,
  and after `actor`, `action`, `subject_type, subject_id` or `tenant`.
- `ScanningAuditLogReader` reads any `AuditRowRepository` by scanning every row, with `AuditQuery::matches()` and
  `AuditQuery::isAfterCursor()`: for an application that keeps its own repository and a small trail, and for tests.
  Each page reads the whole trail.

Both give the same pages for the same queries; the bundle's tests hold them to it.
