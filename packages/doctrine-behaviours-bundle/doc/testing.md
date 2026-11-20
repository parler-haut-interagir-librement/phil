# Testing

| Area | What is covered |
|---|---|
| `PhilDoctrineBehavioursBundle`, `BehaviourServices` | Every behaviour on by default, each off, the event tags, the priority of the soft delete, the services and their arguments, the erasers on the `doctrine` registry, `kernel.reset` on `TreeListener` and `BlameSuspension`, the policy of the personal fields and its cipher, the filters added as the configurations say, nothing without DoctrineBundle, the errors for what a behaviour lacks |
| `SuspendBlameDuringErasurePass`, `SuspendBlame`, `SuspendBlameWithExport` | Every eraser decorated, an eraser that exports kept an exporter, a class given by a parameter, nothing decorated with the suspension off; the erasure run with the blame suspended, the module and the export passed through |
| `SecurityBlameResolver` | Nobody without a token or without the security bundle, the user's identifier with one, the impersonator under `switch_user`, the method `blame.user_method` names |
| `SecurityOwnerResolver` | Nobody without a token, the method named, the account acted as under `switch_user`, an e-mail address refused |
| `SecurityVersionRestoreGuard` | The right asked with the record as subject, the 403, nobody without Security |
| `BlamePrivacy`, `OwnerPrivacy`, `VersionsPrivacy`, `PersonalDataFields` | The rows of a person counted by table, the erasure by a pseudonym or by nobody, the report of every table, a subject with an `@` owning nothing; a `#[PersonalData]` field left out of a version, the versions of an owner and of a blamed person redacted before the name is replaced, read in the raw row of `phil_record_version` after the erasure |
| `StripVersionsCommand` | The versions already written counted by a dry run, then stripped of their personal fields, none marked redacted |
| The search index | `phil/search-bundle` beside the soft delete: a removed page dropped from the index while its row stays, the index listening before the soft delete, a purged page gone from both |
| A whole application | FrameworkBundle, DoctrineBundle and `phil/slug-bundle` on SQLite in memory, the bundle configured in YAML: an entity stamped, placed in its tree, soft deleted and hidden by the filter, and the tree read; a record with a dated identifier and a unique slug, published through `workflow.registry` as a scheduler would, frozen, forked, deactivated, and refused going back; steps ordered within their checklist and moved; a slug unique within its locale; a translation written, read back along a fallback chain, with its unique index |
| Instants | Every instant column the library maps written in UTC from a clock in another time zone, read back as the type the library declares says: the expectations follow that type, never one written in the test |
| On real servers | `tests/Integration` on MySQL 8.4 and PostgreSQL 17, from `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, skipped without them: four slugs of one locale that differ only by case or accent are each found by their own spelling only; on MySQL the keys are in `utf8mb4_bin`, and the tables of the previous version reach it through the schema diff |
| The test application | `lint:container`, `lint:expressions` and `lint:yaml` through `phil-dev lint-apps` |

```sh
vendor/bin/phpunit packages/doctrine-behaviours-bundle/tests
vendor/bin/phil-dev lint-apps doctrine-behaviours-bundle
```
