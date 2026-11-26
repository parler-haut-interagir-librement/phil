# {{context}}

The bounded context {{context}}: what it models, in a few sentences, and the words it uses.

| Layer | Namespace | Holds |
|---|---|---|
| `Domain` | `{{namespace}}\Domain` | Value objects, identifiers, entities of the model, domain events and ports: plain PHP, never services |
| `Application` | `{{namespace}}\Application` | Commands, queries and their handlers, on the buses of `phil/ddd-bundle` |
| `Infrastructure` | `{{namespace}}\Infrastructure` | Controllers, adapters of the ports, Doctrine entities and types |

The context uses its own layers and those of `Shared`, never another context.
