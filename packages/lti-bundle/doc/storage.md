# Storage

With `store: dbal`, the bundle keeps its state in seven tables of one DBAL connection, under the prefix `table_prefix`.
Identifiers compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, `C` on PostgreSQL. Moments are integers of
seconds since the epoch, in UTC.

| Table | Columns | What it keeps |
|---|---|---|
| `phil_lti_nonce` | `scope`, `value_hash`, `expires_at` | The values used once — the nonces of launches and deep linking responses, the `jti` of assertions —, by their SHA-256. The primary key makes the check and the write one step. |
| `phil_lti_login_state` | `state_hash`, `nonce`, `issuer`, `client_id`, `target`, `expires_at` | The login states of the tool, by the SHA-256 of the state; a state is taken by deleting its row. |
| `phil_lti_access_token` | `token_hash`, `client_id`, `scopes`, `expires_at` | The access tokens of the platform, by the SHA-256 of their value only. |
| `phil_lti_key` | `kid`, `sealed`, `created_at`, `retired_at` | The keys, each private key sealed by the KMS as the JSON of its private JSON Web Key. |
| `phil_lti_line_item` | `id`, `client_id`, `context_id`, `score_maximum`, `label`, `resource_id`, `resource_link_id`, `tag`, `start_at`, `end_at`, `grades_released` | The line items of the platform. |
| `phil_lti_score` | `line_item_id`, `user_id`, `payload`, `scored_at` | The latest score of each learner on each line item, as AGS writes it. |
| `phil_lti_pending_score` | `id`, `issuer`, `client_id`, `line_item`, `payload`, `created_at` | The scores the tool still has to send. |

With the ORM, `LtiSchemaListener` adds the tables to the schema the ORM generates for the connection, so that
`doctrine:migrations:diff` writes their migration. Without it, `phil:lti:schema` shows the SQL of the missing tables, and
`--force` runs it; a table that is there is left as it is.

## The purge

`phil:lti:purge` removes the nonces, login states and access tokens that expired; run it daily. The keys retired before
the overlap are removed by `phil:lti:rotate-keys`. Line items and scores stay until a tool deletes them, or the
application does.

## In memory

With `store: memory`, every store is in the process, emptied between the requests of a worker, and the keys come from
the configuration: for tests, never for a site.
