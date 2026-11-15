# Testing

| Area | What is covered |
|---|---|
| `PhilDoctrineBehavioursBundle`, `BehaviourServices` | Every behaviour on by default, each off, the event tags, the services and their arguments, `kernel.reset` on `TreeListener`, the filters added as the configurations say, nothing without DoctrineBundle, the errors for what a behaviour lacks |
| `SecurityBlameResolver` | Nobody without a token or without the security bundle, the user's identifier with one, the impersonator under `switch_user`, the method `blame.user_method` names |
| `SecurityOwnerResolver` | Nobody without a token, the method named, the account acted as under `switch_user`, an e-mail address refused |
| `SecurityVersionRestoreGuard` | The right asked with the record as subject, the 403, nobody without Security |
| `BlamePrivacy`, `OwnerPrivacy` | The rows of a person counted by table, the erasure by a pseudonym or by nobody, the report of every table, a subject with an `@` owning nothing |
| A whole application | FrameworkBundle, DoctrineBundle and `phil/slug-bundle` on SQLite in memory, the bundle configured in YAML: an entity stamped, placed in its tree, soft deleted and hidden by the filter, and the tree read; a record with a dated identifier and a unique slug, published through `workflow.registry` as a scheduler would, frozen, forked, deactivated, and refused going back; steps ordered within their checklist and moved; a slug unique within its locale; a translation written, read back along a fallback chain, with its unique index |
| Instants | Every instant column the library maps written in UTC from a clock in another time zone, read back as the type the library declares says: the expectations follow that type, never one written in the test |
| The test application | `lint:container`, `lint:expressions` and `lint:yaml` through `phil-dev lint-apps` |

```sh
vendor/bin/phpunit packages/doctrine-behaviours-bundle/tests
vendor/bin/phil-dev lint-apps doctrine-behaviours-bundle
```
