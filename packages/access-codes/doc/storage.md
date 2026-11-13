# Keys and storage

## The keyed hash

`CodeHasher` turns a code into what the store keeps: the HMAC-SHA-256 of its canonical form under the application's
key, written `$hmac-sha256$v=1$` and 64 lowercase hexadecimal digits, 81 characters. Hexadecimal compares the same
under a case-insensitive collation, MySQL's default. A copy of the table opens nothing without the key.

```php
use Phil\AccessCodes\CodeHasher;

$hasher = new CodeHasher($activeKey, $previousKey);
$hasher->hash('7KQ2M9XDH4TBH');         // under the active key
$hasher->candidates('7KQ2M9XDH4TBH');   // under every key, the active one first
$hasher->matches($stored, '7KQ2M9XDH4TBH');
```

## Rotating the key

The keys are an ordered list: the first hashes the codes of new batches, and every key finds a code. To rotate, put
the new key first and keep the old one after it; the batches created under the old key keep working. Once every
batch created under a key has expired or been revoked, drop that key. Each key is at least 32 bytes, eight keys at the
most, none given twice.

A code is looked up by its hash under each key at once. The lookup is a database index, or a hash table in memory,
whose timing says nothing of a code since the hash is keyed; `matches()` compares every key with `hash_equals()`.

## The stores

| Store | For |
|---|---|
| `InMemoryCodeStore` | Tests and scripts that create and redeem in one run |
| `Dbal\DbalCodeStore` | Two tables of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite |

### The tables

```php
use Phil\AccessCodes\Dbal\DbalCodeStore;

$store = new DbalCodeStore($connection);   // phil_access_code_batch and phil_access_code
$store = new DbalCodeStore($connection, 'shop_code_batch', 'shop_code');
```

`configureSchema(Schema $schema): Schema` adds the tables to a schema that lacks them: call it from a Doctrine
migration, or from a listener of the ORM's `postGenerateSchema` event so that `doctrine:migrations:diff` writes them.

| Table | Columns |
|---|---|
| `phil_access_code_batch` | `id` (key), `name`, `size`, `created_at`, `expires_at`, `revoked_at` |
| `phil_access_code` | `hash` (key), `batch_id` (indexed), `expires_at`, `redeemed_at`, `redeemed_by`, `revoked_at` |

Instants are Unix timestamps. The expiry is copied into each code's row, so that a redemption reads one row. A batch
is added in one transaction, its codes inserted 500 rows per statement; a revocation marks the batch and its
unredeemed codes in one transaction. A table name is checked before any SQL: up to 63 lowercase letters, digits or
`_`, not starting with a digit.

`redeemed_by` holds the redeemer's stable identifier, never an address. Deleting a member's data is deleting or
blanking their rows by that identifier.
