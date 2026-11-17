# Storage

With `store: dbal`, the bundle keeps its state in eight tables of one DBAL connection, under the prefix `table_prefix`.
Identifiers compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, `C` on PostgreSQL. Moments are integers of
seconds since the epoch, in UTC, but the moment of a score, in microseconds.

| Table | Columns | What it keeps |
|---|---|---|
| `phil_lti_nonce` | `scope`, `value_hash`, `expires_at` | The values used once — the nonces of launches and deep linking responses, the `jti` of assertions —, by their SHA-256. The primary key makes the check and the write one step. |
| `phil_lti_login_state` | `state_hash`, `nonce`, `issuer`, `client_id`, `target`, `expires_at` | The login states of the tool, by the SHA-256 of the state; a state is taken by deleting its row. |
| `phil_lti_access_token` | `token_hash`, `client_id`, `scopes`, `expires_at` | The access tokens of the platform, by the SHA-256 of their value only. |
| `phil_lti_key` | `kid`, `sealed`, `created_at`, `retired_at` | The keys, each private key sealed by the KMS as the JSON of its private JSON Web Key. |
| `phil_lti_line_item` | `id`, `client_id`, `context_id`, `score_maximum`, `label`, `resource_id`, `resource_link_id`, `tag`, `start_at`, `end_at`, `grades_released` | The line items of the platform. |
| `phil_lti_score` | `line_item_id`, `user_id`, `payload`, `scored_at` | The latest score of each learner on each line item, as AGS writes it. A score is written by an update guarded by `scored_at`, else an insert, and the guarded update again when another write inserted first: no write replaces a score by an older one. |
| `phil_lti_pending_score` | `id`, `issuer`, `client_id`, `line_item`, `payload`, `created_at`, `dispatched_at`, `claimed_at`, `refused_at`, `refusal` | The scores the tool still has to send: when the bus took the message, when a recovery last claimed the score — by an update guarded by that moment —, and when and why the platform refused it for good. |
| `phil_lti_deep_linking` | `id`, `client_id`, `payload`, `received_at`, `expires_at`, `handled_at` | The deep linking responses the platform verified, by their key: their claims in JSON, until they expire, and when the application handled them. |

With the ORM, `LtiSchemaListener` adds the tables to the schema the ORM generates for the connection, so that
`doctrine:migrations:diff` writes their migration. Without it, `phil:lti:schema` shows the SQL that creates the tables
missing and adds to the tables there the columns and indexes they lack, and `--force` runs it; no column is changed or
dropped.

## Upgrading from 0.1

Version 0.2 adds the table `phil_lti_deep_linking`, the nullable columns `dispatched_at`, `claimed_at`, `refused_at` and
`refusal` of `phil_lti_pending_score`, and its indexes `phil_lti_pending_score_created` and
`phil_lti_pending_score_refused`; the other tables are as they were.

- With the ORM, `doctrine:migrations:diff` writes the migration, and `doctrine:migrations:migrate` runs it.
- Without it, `phil:lti:schema` shows the statements, and `phil:lti:schema --force` runs them.

The scores pending at the upgrade have no `dispatched_at`: once the delay of `phil:lti:recover-scores` passed, the
recovery dispatches their message again, harmlessly for those whose message was taken already — a score sent twice is
kept once by its timestamp, and a message whose score was sent finds nothing to send.

## The purge

`phil:lti:purge` removes the nonces, login states, access tokens and deep linking responses that expired, and the scores
refused for good before `tool.refused_score_retention`; run it daily. `phil:lti:recover-scores` sends again the scores
whose message was never taken; run it every few minutes. The keys retired before
the overlap are removed by `phil:lti:rotate-keys`. Line items and scores stay until a tool deletes them, or the
application does.

## In memory

With `store: memory`, every store is in the process, emptied between the requests of a worker, and the keys come from
the configuration: for tests, never for a site.
