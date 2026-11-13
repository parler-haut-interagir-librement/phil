# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\AccessLinks\AccessLinks` | `final readonly class` | The library in one object: `invite()`, `reissue()`, `open()`, `respond()`, `revoke()`, `dueReminders()`, `remind()`, `invitation()`, `counts()` |
| `Phil\AccessLinks\Invitation` | `final readonly class` | One recipient invited to one campaign: moments, answer, reminders, revocation |
| `Phil\AccessLinks\InvitationAccess` | `final readonly class` | What a link opened: the invitation and whether it is editable |
| `Phil\AccessLinks\IssuedLink` | `final readonly class` | A newly issued link: invitation, token and address, never serialized |
| `Phil\AccessLinks\CampaignCounts` | `final readonly class` | Invited, responded, revoked and pending invitations of a campaign |
| `Phil\AccessLinks\LinkToken` | `final class` | Draws and reads the tokens of 32 bytes |
| `Phil\AccessLinks\TokenHasher` | `final readonly class` | The HMAC-SHA-256 of a token under an ordered list of keys |
| `Phil\AccessLinks\LinkFormat` | `final readonly class` | The invitation page's address with a token |
| `Phil\AccessLinks\ReminderSchedule` | `final readonly class` | When the reminders fall due |
| `Phil\AccessLinks\Contract\InvitationStore` | interface | Where the invitations and the hashes of their links live |
| `Phil\AccessLinks\Contract\ReminderSender` | interface | How the application reaches a recipient |
| `Phil\AccessLinks\InMemoryInvitationStore` | `final class` | The invitations of one process |
| `Phil\AccessLinks\Dbal\DbalInvitationStore` | `final readonly class` | The invitations in two tables of a Doctrine DBAL connection |
| `Phil\AccessLinks\Messenger\SendInvitationReminder` | `final readonly class` | The message of one due reminder: the invitation's identifier |
| `Phil\AccessLinks\Messenger\SendInvitationReminderHandler` | `final readonly class` | Sends the reminder a message asks for |
| `Phil\AccessLinks\Messenger\ReminderDispatcher` | `final readonly class` | Dispatches one message per due reminder |
| `Phil\AccessLinks\Exception\AccessLinksException` | interface | Every exception of the library |
| `Phil\AccessLinks\Exception\InvalidConfiguration` | `final class` | A value the library cannot work with |
| `Phil\AccessLinks\Exception\InvalidToken` | `final class` | A text that is not a link token |
| `Phil\AccessLinks\Exception\LinkNotUsable` | `final class` | A link that opens nothing |
| `Phil\AccessLinks\Exception\InvitationClosed` | `final class` | An answer after the closure |
| `Phil\AccessLinks\Exception\AlreadyInvited` | `final class` | A recipient invited twice to a campaign |
| `Phil\AccessLinks\Exception\UnknownInvitation` | `final class` | An invitation the store does not hold |
