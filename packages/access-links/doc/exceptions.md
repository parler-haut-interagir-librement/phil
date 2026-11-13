# Exceptions

Every exception of the library implements `Phil\AccessLinks\Exception\AccessLinksException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidConfiguration` | `InvalidArgumentException` | A value the library cannot work with: a key, a campaign, a recipient, moments out of order, an answer, a schedule, an address, a table name, the sixteen links reached |
| `InvalidToken` | `InvalidArgumentException` | `LinkToken::parse()` refuses a text; `open()` and `respond()` turn it into "opens nothing" |
| `LinkNotUsable` | `RuntimeException` | `respond()` with a link that opens nothing: malformed, unknown, revoked, not yet valid or expired |
| `InvitationClosed` | `RuntimeException` | `respond()` after the closure |
| `AlreadyInvited` | `RuntimeException` | `invite()` for a recipient invited to the campaign before |
| `UnknownInvitation` | `RuntimeException` | An invitation identifier the store does not hold |

No message repeats a token or a key; `LinkNotUsable` does not say why a link opens nothing.
