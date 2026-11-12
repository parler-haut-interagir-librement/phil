# Exceptions

Every exception of the bundle implements `Phil\FormsAutosave\Domain\Exception\AutosaveException`.

| Exception | Thrown when | Endpoint answer |
|---|---|---|
| `Domain\Exception\InvalidDraft` | A key, a version, fields, a revision or a stored row a draft cannot be made of; a subject that is no text, number or null | `400` |
| `Application\Exception\RevisionConflict` | A save names another revision than the one kept; `$current` is that revision, or null when the draft went | `409` |
| `Application\Exception\DraftTooLarge` | The encoded draft is larger than `server.max_bytes` | `413` |

The container stops with a `LogicException` of the DependencyInjection component when the server store lacks the CSRF
protection, an owner or a cipher; see [Configuration](configuration.md).
