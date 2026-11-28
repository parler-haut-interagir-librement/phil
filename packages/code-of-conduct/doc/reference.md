# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `CodeOfConduct` | `final readonly class` | Publishes the versions of a code, records acceptances, answers the status of a member |
| `CodeVersion` | `final readonly class` | One published version: scope, number, title, text, kind, date, hash |
| `ChangeKind` | `enum` | Major, which asks for a new acceptance, or minor |
| `Acceptance` | `final readonly class` | A member accepted a version: its number, its hash, when |
| `AcceptanceStatus` | `enum` | Where a member stands: no code, not accepted, outdated, accepted |
| `Reports` | `final readonly class` | Files, lists, opens, annotates, reviews, resolves and dismisses reports |
| `Report` | `final readonly class` | A report: scope, reporter, people named, sealed text, version, status, outcome, people who stepped aside |
| `ReportStatus` | `enum` | Received, in review, resolved, dismissed, and the moves between them |
| `Outcome` | `enum` | What was decided, from no action to a permanent exclusion |
| `ReportNote` | `final readonly class` | A note of a responsible person, sealed |
| `ReportView` | `final readonly class` | A report opened: its text and its notes |
| `NoteView` | `final readonly class` | A note opened |
| `ReportReceipt` | `final readonly class` | What the reporter sees: status and dates |
| `ReportPage` | `final readonly class` | A page of the reports a person may handle, and where the next starts |
| `Contract\CodeVersionStore` | interface | The versions of the codes |
| `Contract\AcceptanceStore` | interface | The acceptances |
| `Contract\ReportStore` | interface | The reports and their notes |
| `Contract\ResponsiblePeople` | interface | The responsible people of a scope |
| `Contract\TextSealer` | interface | Seals and opens a text under a context |
| `KeyManagement\KeyManagementTextSealer` | `final readonly class` | The sealer over Symfony's KeyManagement envelope encryption |
| `Memory\InMemoryCodeVersionStore` | `final class` | The versions in memory, for tests |
| `Memory\InMemoryAcceptanceStore` | `final class` | The acceptances in memory, for tests |
| `Memory\InMemoryReportStore` | `final class` | The reports in memory, for tests |
| `Event\CodeVersionPublished` | `final readonly class` | A version was published |
| `Event\CodeAccepted` | `final readonly class` | A member accepted a version |
| `Event\ReportFiled` | `final readonly class` | A report was filed |
| `Event\ReportViewed` | `final readonly class` | A responsible person opened a report |
| `Event\ReportNoteAdded` | `final readonly class` | A note was added |
| `Event\ReportStatusChanged` | `final readonly class` | A report moved, with its outcome once resolved |
| `Event\ReportRecused` | `final readonly class` | A responsible person stepped aside |
| `Exception\CodeOfConductException` | interface | Every exception of the package |
| `Exception\InvalidCodeOfConduct` | `final class`, `InvalidArgumentException` | A value out of its bounds |
| `Exception\ActionRefused` | `final class`, `RuntimeException` | An action the state refuses |
| `Exception\ReportUnavailable` | `final class`, `RuntimeException` | A report that does not exist or may not be seen, the same message |
| `Exception\UnreadableText` | `final class`, `RuntimeException` | A sealed text that does not open |

`Identifier` and `Text` are internal: the rules every identifier and every free text follow.
