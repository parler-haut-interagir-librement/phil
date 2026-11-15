# Doctrine

With `source: doctrine`, the versions are kept in the table `phil_accessibility_statement` of the connection named by
`connection`:

| Column | Type | Holds |
|---|---|---|
| `version` | integer, primary key | The number of the version. |
| `published_at` | `datetime_utc_immutable` | When it was published, written and read back in UTC whatever the time zone of the server. |
| `document` | text | The statement as JSON, as `Statement::toArray()` writes it. |

The table is added to the schema the ORM generates for its connection: `doctrine:migrations:diff` writes the migration
that creates it, and `doctrine:schema:validate` does not ask to drop it.

A version is inserted once; the primary key refuses a second version of the same number, so that two regenerations at
once end in one refusal (`StatementNotStored`). Nothing updates or deletes a row.

The statement of the configuration, when there is one, is a version too: in force while the table holds none, and
listed among the others while the table holds no version of its number. A site starts with its first version written
in the configuration and lets the command add the next ones.

The list of the versions reads only the numbers and the moments of publication, never the documents: a page view
reads one statement whatever the number of versions. A row whose document no longer reads as a statement is found as
no version, and its address answers 404; when it is the newest, no version is in force and the page answers 404,
rather than showing an older statement as if it were current. Each time such a row is read, a warning goes to the
`logger` service: `The accessibility statement version {version} does not read as a statement: it is served as no
version.`, with the number of the version as its only context, never what the document holds.
