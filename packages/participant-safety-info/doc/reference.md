# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `SafetyInfoVault` | `final readonly class` | Every path to the information: submit, read, break the glass, copy, erase, journal, purge |
| `SafetyInfo` | `final readonly class` | What a participant tells: up to three contacts, allergies, needs, notes |
| `EmergencyContact` | `final readonly class` | A person to call: a name, a relation, a telephone number |
| `SafetyInfoCodec` | `final class` | The plaintext form of the information, JSON of version 1, decoded strictly |
| `SealedSafetyInfo` | `final readonly class` | What the store keeps: the sealed text, when it was sealed, until when it is kept |
| `AccessWindow` | `final readonly class` | When the responsible people of an event may read |
| `AccessRecord` | `final readonly class` | One reading by someone else: who, why, when, the reason of a broken glass |
| `AccessReason` | `enum` | Why a reading was allowed: responsible, or a broken glass |
| `Role` | `enum` | What a person is to an event: responsible, team, or none |
| `Contract\SafetyInfoStore` | interface | The sealed records |
| `Contract\SafetyInfoSealer` | interface | Seals and opens a text under a context |
| `Contract\EventRoles` | interface | The role of a person for an event |
| `Contract\AccessWindows` | interface | The access window of an event |
| `Contract\AccessJournal` | interface | The journal of the readings |
| `KeyManagement\KeyManagementSealer` | `final readonly class` | The sealer over Symfony's KeyManagement envelope encryption |
| `Memory\InMemorySafetyInfoStore` | `final class` | The store in memory, for tests |
| `Memory\InMemoryAccessJournal` | `final class` | The journal in memory, for tests |
| `Event\SafetyInfoSubmitted` | `final readonly class` | A participant submitted or replaced their information |
| `Event\SafetyInfoRead` | `final readonly class` | Someone else read it, and why |
| `Event\SafetyInfoErased` | `final readonly class` | A participant erased it |
| `Exception\ParticipantSafetyInfoException` | interface | Every exception of the package |
| `Exception\InvalidSafetyInfo` | `final class`, `InvalidArgumentException` | A value out of its bounds |
| `Exception\AccessDenied` | `final class`, `RuntimeException` | A reading or a submission refused, the same message whatever the reason |
| `Exception\UnreadableSafetyInfo` | `final class`, `RuntimeException` | A record that does not open or does not decode |

`Identifier` is internal: the rule every identifier follows.
