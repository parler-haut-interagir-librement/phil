# Reference

Every class and service of the bundle, with its role.

| Class | Role |
|---|---|
| `PhilModerationBundle` | The bundle and its configuration `phil_moderation` |
| `Domain\ValueObject\MemberId` | A member by a stable identifier, never an address |
| `Domain\ValueObject\TargetReference` | A type and the identifier of a content |
| `Domain\ValueObject\Report`, `Decision`, `MemberNote`, `QueueItem` | A report, a decision, a note on a member, a line of the queue |
| `Domain\ValueObject\StatementOfReasons` | The statement of reasons addressed to the author of a restricted content |
| `Domain\Status\ReportSource`, `DecisionAction`, `Ground` | Where a report comes from; what a decision does and whether it restricts; what a restriction rests on |
| `Domain\Contract\ReportRepository`, `DecisionRepository`, `MemberNoteRepository`, `SandboxRepository`, `StatementRepository` | The stores |
| `Domain\Identifiers` | Identifiers, codes and bounded texts |
| `Application\Reports` | Files the reports of members and of the bundle |
| `Application\Decisions` | Decides on a target; the history |
| `Application\StatementsOfReasons`, `Grounds`, `ReasonGrounds`, `Redress` | The statements of reasons; what a moderator gives; the grounds of each reason; the ways to contest |
| `Application\ModerationQueue`, `QueueEntry` | The single queue; a target under review |
| `Application\Submissions`, `Submission` | The hook of a new content |
| `Application\TemporaryHides` | Hiding by trusted members |
| `Application\MemberNotes` | The notes of the moderation on members |
| `Application\PublicJournal`, `JournalEntry` | The public journal |
| `Application\ReportSurge` | Whether reports surge |
| `Application\Role`, `Roles`, `Moderator`, `Reasons` | The roles, a moderator, the reasons |
| `Application\ModerationTargets`, `TargetView`, `ProfileTarget` | The registered targets, what the queue shows, profiles as a target |
| `Application\Screening`, `ScreeningVerdict` | What the watched words make of a text |
| `Application\FirstPostSandbox`, `NoWordScreen`, `NoTrustedHiders`, `NoCurrentMember` | The defaults of the ports |
| `Application\Contract\*` | The ports: `ModerationTarget`, `MemberProfiles`, `CurrentMember`, `CurrentModerator`, `ModerationMember`, `WordScreen`, `TrustedHiders`, `SandboxPolicy` |
| `Application\Event\*` | `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember`, `StatementIssued` |
| `Application\Exception\UnknownTarget`, `ModerationRefused` | The refusals of the services |
| `Infrastructure\Doctrine\*` | The DBAL stores, `ModerationTables`, `ModerationSchemaListener`, `Rows` |
| `Infrastructure\Security\TokenCurrentMember`, `TokenCurrentModerator` | The member and moderator of the security token |
| `Infrastructure\Http\AdminController`, `ReportController`, `JournalController`, `StatementController` | The pages |
| `Infrastructure\EasyAdmin\ModerationAdmin` | The menu items and routes inside EasyAdmin |
| `Infrastructure\IncidentMode\IncidentPanel` | The incident mode in the back office |
| `Infrastructure\IncidentMode\CurrentMemberRestrictions` | Whether a restriction of an incident applies to the member of the current request, through `IncidentRestrictions` of `phil/incident-mode` |
| `Infrastructure\Notifications\StatementNotifier`, `StatementSubjects` | The notification of a statement, and what its subject stands for, with `phil/notifications-bundle` |
| `Infrastructure\Privacy\ModerationPrivacy` | The export and the erasure |
| `Infrastructure\Twig\ModerationExtension`, `KitComponents` | The Twig functions; the names of the recipes |
| `Infrastructure\DependencyInjection\ModerationServices`, `ModerationPass`, `KitSubdirectoryPass` | The services and passes |
