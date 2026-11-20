# Choosing a control

Deleting, keeping for a period, anonymising, encrypting and pseudonymising are different tools. Each answers one problem
and leaves others open; none of them is a privacy switch. This page says which tool of the bundle answers which
problem, and where it stops.

## The scale this bundle is made for

The bundle is made for **tier 1**: one application, one relational database, its backups, its logs, its workers, and a
few external services it calls. At that scale, the person's data lives in tables the application owns, and erasing it
means changing those tables, the derived copies the application keeps, and asking the external services to do the
same.

Two larger tiers exist: several services each owning their data, then a data platform where copies flow into analytics.
They add streams, event stores and warehouses that keep data out of reach of an `UPDATE`. The bundle does not cover
them; an application that grows into them adds controls of its own, and keeps the ones below for its own tables.

## The grid

| Problem | Tool of the bundle | Limit |
|---|---|---|
| A person asks to be erased | `PersonalDataEraser` per module, run by `SubjectErasure` or by the [erasure workflow](erasure-workflow.md) | Erases what the erasers know of. A table, a cache, a log or a copy that no eraser covers keeps the data. |
| A module erases through an external service | `ExternalPersonalDataEraser`, acknowledged by the service | The service's word is the proof: the bundle cannot check what the service kept. |
| Records outlive their purpose | The retention schedule and `RetentionRun`, store by store ([retention](retention.md)) | A store without a rule is never purged; the schedule only purges what a purger answers for. |
| A record must stay, but not the person in it | An eraser that redacts or pseudonymises in place, reported as `Redacted` or `Pseudonymised` | The record is still personal data: the other fields, a pseudonym and its link back still point to the person. |
| A record must stay, and nothing in it may point to the person | An eraser that reduces it to a skeleton, reported as `Anonymised` | Only when someone judged and wrote down that nothing left singles the person out, alone or with what the site or anyone else can reasonably use. A rare value, a free-text field or a date of birth often does. |
| A copy of the database for development or support | `phil:privacy:anonymize` ([anonymization](anonymization.md)) | Masks the columns it is told about; a column it is not told about is copied as it is. |
| A stolen copy of the database | Encrypted columns, `#[Encrypted]` ([encrypted columns](encrypted-columns.md)) | Protects the columns at rest, not the application: whoever runs the application, or reaches its keys, reads them. |
| Finding a row by an encrypted value | A blind index, `#[BlindIndexed]` | Equal values give equal indexes: whoever reads the table sees which rows share a value, and a small domain, such as a postal code, can be tried in full. |
| Logs that repeat what the requests carry | Log redaction ([log redaction](log-redaction.md)) | Redacts the keys and patterns it is given; a value inside a free-text message passes. |
| Events and messages that outlive the rows | phil/outbox payloads made of identifiers, messages made of identifiers | Holds while each package keeps to it; a payload that carries a value keeps it until the outbox purges the record. |

## Pseudonymised is not anonymised

A pseudonym, a token, a keyed hash or an internal identifier still identifies the person for whoever holds the link
back: the key, the salt, or the table that maps it. Pseudonymised data stays personal data. Report a store as
`Pseudonymised`, and keep `Anonymised` for a skeleton judged non-identifiable. The register and the reports mark the
difference: every effect but `Deleted` and `Anonymised` says that personal data is left (`personal_data_left`).

## Ruled out

- **A token vault.** Replacing every personal value with a token kept in a separate vault moves the data to the vault,
  where an erasure still has to reach it, and puts the vault on the path of every read. At tier 1, the application's
  own tables, erased in place, are simpler and erase as much.
- **Transparent database encryption as an erasure.** Encryption of the database files by the database server (TDE)
  protects the disks and the backups against theft. The server decrypts every row for whoever connects: it neither
  erases nor limits what the application reads.
- **Destroying a key as an erasure.** Destroying the key of encrypted columns revokes access to what it sealed; whether
  that counts as erasing depends on who else held the key and on what law applies, and other copies — backups, logs,
  exports — are untouched.

## Backups

A backup taken before an erasure holds the person's data until the backup expires. Keep backups for a short, written
period; check that they expire as written; and after a restore, erase again the people whose erasure the restored copy
predates, from the erasure requests and account deletions the bundle keeps. An erasure is idempotent, so running it
again on a person already erased does nothing more.
