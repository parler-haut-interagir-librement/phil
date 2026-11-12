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

## Stores

| Port | Doctrine | Memory |
|---|---|---|
| `SegmentRepository` | `DoctrineSegmentRepository` | `InMemorySegmentRepository` |
| `TermRepository` | `DoctrineTermRepository` | `InMemoryTermRepository` |
| `Phil\TranslationMemory\Memory\MemoryStore` | `DoctrineMemoryStore` | the library's `InMemoryStore` |
| `Contract\CommentRepository` | `DoctrineCommentRepository` | `InMemoryCommentRepository` |

The Doctrine stores flush on each save and removal — a save of several segments flushes once. The stores in memory
keep their rows for the life of the service: for tests, and for previews that must not write.
