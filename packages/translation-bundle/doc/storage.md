# Storage

## Tables

| Table | Key | Columns |
|---|---|---|
| `phil_translation_segment` | `document`, `target_language`, `segment_key` | `source_language`, `position`, `source`, `fingerprint`, `state`, `target`, `translated_source`, `updated_at`, `translated_by`, `reviewed_by`, `machine`, `flags` |
| `phil_translation_memory` | `source_language`, `target_language`, `fingerprint` | `length`, `source`, `target`, `origin`, `updated_at`; index on `source_language`, `target_language`, `length` |
| `phil_translation_term` | `source_language`, `target_language`, `term` | `translations`, `forbidden`, `forms` and `translation_forms` (JSON lists), `case_sensitive`, `note` |
| `phil_translation_comment` | `id`, generated | `document`, `target_language`, `segment_key`, `author`, `body`, `created_at`; index on `document`, `target_language`, `segment_key` |

Every key but a comment's is natural: a segment is its document, language and key; a memory unit its languages and
source fingerprint; a term its languages and text. `translated_by`, `reviewed_by` and `author` hold the identifiers the
application's `Translators` gives, at most 255 bytes, never a name; `flags` holds the flags of the checks as text, at
most 1024 bytes. The memory is looked up by its key for exact matches, and by its length index for
candidates of fuzzy matches. Times are written in whole seconds by the clock.

## Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A segment, a memory unit, a term and a comment are
found by their keys as they were written: the segment `Title` of a document is never `title`, the glossary holds
`Apple` and `apple` as two terms, and the work of the translator `Ann` is never listed for `ann`. A term marked
case-insensitive still matches the text in any case: that is the matching of the glossary, not of the table.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_translation_segment` | `document`, `source_language`, `target_language`, `segment_key`, `translated_by`, `reviewed_by` |
| `phil_translation_memory` | `source_language`, `target_language`, `fingerprint` |
| `phil_translation_term` | `source_language`, `target_language`, `term` |
| `phil_translation_comment` | `document`, `target_language`, `segment_key`, `author` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes
the migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. On
PostgreSQL and SQLite the diff is empty.

The change only makes values more distinct, so no existing row breaks a unique key. It parts what the
case-insensitive collation joined: rows whose keys differ only by case, which a lookup by either spelling returned
together, are each found by their own spelling from the migration on, and a key the application looks up in
another case than it wrote stops matching. Before migrating, list the keys written in more than one case:

```sql
SELECT c.id FROM phil_translation_comment c
WHERE NOT EXISTS (SELECT 1 FROM phil_translation_segment s WHERE CAST(s.document AS BINARY) = CAST(c.document AS BINARY)
    AND CAST(s.target_language AS BINARY) = CAST(c.target_language AS BINARY) AND CAST(s.segment_key AS BINARY) = CAST(c.segment_key AS BINARY));
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.

## Stores

| Port | Doctrine | Memory |
|---|---|---|
| `SegmentRepository` | `DoctrineSegmentRepository` | `InMemorySegmentRepository` |
| `TermRepository` | `DoctrineTermRepository` | `InMemoryTermRepository` |
| `Phil\TranslationMemory\Memory\MemoryStore` | `DoctrineMemoryStore` | the library's `InMemoryStore` |
| `Contract\CommentRepository` | `DoctrineCommentRepository` | `InMemoryCommentRepository` |

The Doctrine stores flush on each save and removal — a save of several segments flushes once. The stores in memory
keep their rows for the life of the service: for tests, and for previews that must not write.
