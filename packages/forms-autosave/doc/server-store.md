# The server store

With `server.enabled`, a signed-in user's drafts are also kept on the server, so that they follow the user to another
device. A visitor's drafts stay in their browser.

## Users

`DraftOwner` names the user: by default `TokenDraftOwner`, the user identifier of Symfony Security. The table keeps
only a keyed hash of it, 64 hexadecimal digits, and every read or write is made under the hash of the current user, so
a user never reaches another's draft, even with its key.

## Revisions and conflicts

Each save names the revision it replaces, null for a first save. The server keeps the draft by compare-and-set: an
insertion fails if the draft exists, a replacement if another revision was saved meanwhile. Either way the endpoint
answers `409` with the revision kept, and the controller reads the server's draft and shows the notice of a newer
draft: the user uses it, or keeps the values in the form, which then replace it.

## Encryption

Each draft is sealed by `DraftSealer` in a context naming its owner's hash and its key, so that a sealed draft copied
to another row does not open. With `server.encrypt` on, `KeyManagementSealer` seals by the envelope encryption of
Symfony's KeyManagement component: a fresh data key per draft wrapped by the master key `server.key` names, the
content encrypted locally with AES-256-GCM, the context authenticated with it, the envelope stored in base64. The
envelope names its master key, so drafts sealed before `server.key` changed still open while the KMS holds the old
key. A draft that no longer opens — altered, moved, under a master key the KMS no longer holds, or while the KMS
cannot be reached — is deleted when read, unless another revision was saved meanwhile. With `server.encrypt: false`,
`PlainSealer` stores what users typed in clear.

## Expiry

A draft is kept `ttl` seconds after its last save. An expired draft is read as none, and a save over it starts a new
one. The expired draft is removed only while it is still the one read, by its revision and its expiry: of two saves
that meet on it, the second answers `409` instead of deleting the first. `phil:forms-autosave:purge` deletes the
expired drafts:

```sh
bin/console phil:forms-autosave:purge
```

## The table

`phil_form_draft`: `owner` and `draft_key`, the primary key; `revision`; `payload`, the sealed content; `saved_at` and
`expires_at`, in UTC, the latter indexed for the purge. It is mapped for Doctrine when the server store is on; the
store writes it by plain queries on its connection, which leave the entity manager open when a race is lost.

### Collation of the key

**The key compares byte by byte**, as PHP compares it. A draft key mixes cases — `DraftKey` takes letters of both
cases —, so the draft `AbcdefghijklmnoP` and the draft `abcdefghijklmnop` are two drafts: neither is read, replaced or
deleted for the other, and saving one never fails on the other's primary key. The owners' hashes are lowercase
hexadecimal, which compares the same under any collation; they take the binary collation all the same, so that the
key of the table never depends on the server.

With the Doctrine store, `BinaryKeysListener` gives `owner` and `draft_key` the binary collation when the ORM loads the
metadata of `Draft` on MySQL or MariaDB; the mapping itself stays the same on every platform. On MySQL and MariaDB,
these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the connection's platform: the
default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's default collations are
deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the default.

#### Upgrading from 0.2

The table of a 0.2 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings `owner` and `draft_key` to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. By hand,
per platform:

```sql
-- MySQL and MariaDB
ALTER TABLE phil_form_draft
    MODIFY owner VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    MODIFY draft_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
-- PostgreSQL: nothing to run, the default collation is deterministic.
-- SQLite: nothing to run, the columns compare bytes.
```

The change only makes values more distinct, so no existing row breaks the primary key. Under the old collation, two
drafts whose keys differed only by case could not both be kept: the second save was answered `409`, and read the
first. From the migration on, each is saved under its own key.
