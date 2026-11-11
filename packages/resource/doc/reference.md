# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Resource\ResourceReference` | `final readonly class` | A kind and an identifier |
| `Phil\Resource\InvalidResource` | `InvalidArgumentException` | A reference against the rules |

## The rules

| Part | Rule |
|---|---|
| `key`, the kind | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter |
| `id`, the identifier | 1 to 255 bytes, anything else free — colons included |

`toString()` writes `kind:identifier`; `fromString()` reads it back, the first colon ending the kind, which holds
none. `equals()` compares both parts.
